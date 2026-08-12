// Shared activity create/edit modal logic.
window.afActivities = (function () {
  const modalEl = document.getElementById('activityModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('activityForm');
  const moveCloneModalEl = document.getElementById('taskMoveCloneModal');
  const moveCloneModal = moveCloneModalEl ? new bootstrap.Modal(moveCloneModalEl) : null;
  let currentId = null;
  let currentType = 'planned';
  let currentComments = [];
  let moveCloneMode = 'move'; // 'move' | 'clone'
  let moveCloneIds = [];

  const API = () => window.AF_BASE_URL + 'api/activities.php';

  // ---- Rich text description ----
  // Unlike the New/Edit Project modals (each initialized once over a
  // textarea that only ever describes one, page-load-fixed project), this
  // same modal — and the same underlying textarea/Quill instance — gets
  // reused for potentially many different tasks in one page session
  // (opening task A, closing, opening task B, opening Create, etc.).
  // afInitRichText() only syncs Quill -> textarea (on the user typing,
  // via its 'text-change' listener); it has no way to notice the textarea's
  // .value being reassigned programmatically. So every place that used to
  // just set the textarea's value now goes through setDescriptionHtml()
  // instead, which pushes the new HTML into the live Quill instance too
  // (mirroring how afInitRichText() itself seeds Quill's very first
  // content from the textarea at init time: `quill.root.innerHTML = ...`).
  const descriptionQuill = window.afInitRichText && window.afInitRichText('am_description');
  function setDescriptionHtml(html) {
    const textarea = document.getElementById('am_description');
    if (textarea) textarea.value = html || '';
    if (descriptionQuill) descriptionQuill.root.innerHTML = html || '';
  }

  // ---- Rich text new-comment field ----
  // Same modal-reuse concern as the description field above: the "add a
  // comment" textarea/Quill instance is shared across every task opened in
  // this page session, so clearing it (after posting, or before loading a
  // different task's comments) must go through setNewCommentHtml() rather
  // than assigning the textarea's .value directly, which Quill would never
  // pick up.
  const newCommentQuill = window.afInitRichText && window.afInitRichText('am_new_comment_body');
  function setNewCommentHtml(html) {
    const textarea = document.getElementById('am_new_comment_body');
    if (textarea) textarea.value = html || '';
    if (newCommentQuill) newCommentQuill.root.innerHTML = html || '';
  }

  // Picking a new day from the native datetime-local calendar widget shouldn't
  // force the user to also manually reset the hour every time — Planned start
  // defaults to 9:00 AM and Target completion to 5:00 PM whenever the DATE
  // portion changes. Changing just the time (without touching the date) is left
  // alone. `dataset.lastDate` tracks the date last seen on each field so a
  // "change" event can tell a real date change apart from a time-only edit;
  // it's kept in sync with syncDatetimeTracking() everywhere the fields get set
  // programmatically (reset/fillForm/openCreate), since programmatic .value
  // assignment doesn't fire "change" on its own.
  function initDefaultTimeOnDateChange(inputId, defaultTime) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.dataset.lastDate = (input.value || '').slice(0, 10);
    input.addEventListener('change', function () {
      const newDate = (input.value || '').slice(0, 10);
      if (newDate && newDate !== input.dataset.lastDate) {
        input.value = newDate + 'T' + defaultTime;
      }
      input.dataset.lastDate = (input.value || '').slice(0, 10);
    });
  }
  initDefaultTimeOnDateChange('am_planned_start_at', '09:00');
  initDefaultTimeOnDateChange('am_target_completion_at', '17:00');

  // Live vacation-conflict check: re-runs whenever the assignee or either
  // date field changes, so switching assignees or rescheduling shows the
  // warning immediately instead of only after the task is saved and
  // reloaded. fillForm() (existing task) short-circuits this with the
  // already-computed a.vacation_conflict from the server instead of an
  // extra round trip; this live check covers every subsequent edit made
  // within the same modal session (including brand-new tasks).
  function formatVacationWarning(tpl, vars) {
    return Object.keys(vars).reduce((s, k) => s.split('{' + k + '}').join(vars[k]), tpl);
  }
  function showVacationWarning(conflict, personName) {
    const el = document.getElementById('am_vacation_warning');
    const textEl = document.getElementById('am_vacation_warning_text');
    if (!el || !textEl) return;
    if (!conflict) {
      el.classList.add('d-none');
      return;
    }
    const tpl = window.AF_I18N_VACATION_WARNING || '{name} is on vacation from {start} to {end}, which overlaps this task\'s scheduled dates.';
    textEl.textContent = formatVacationWarning(tpl, {
      name: personName || 'The assignee', start: conflict.start_date, end: conflict.end_date,
    });
    el.classList.remove('d-none');
  }
  function checkVacationConflict() {
    const assigneeSelect = document.getElementById('am_assignee_id');
    const startInput = document.getElementById('am_planned_start_at');
    const endInput = document.getElementById('am_target_completion_at');
    if (!assigneeSelect || !startInput) return;
    const assigneeId = assigneeSelect.value;
    const start = startInput.value;
    const end = (endInput && endInput.value) || start;
    if (!assigneeId || !start) {
      showVacationWarning(null);
      return;
    }
    afFetch(API() + '?action=check_vacation_conflict&assignee_id=' + encodeURIComponent(assigneeId) +
      '&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
      .then((res) => {
        const opt = assigneeSelect.options[assigneeSelect.selectedIndex];
        showVacationWarning(res.conflict, opt ? opt.text : '');
      })
      .catch(() => {});
  }
  ['am_assignee_id', 'am_planned_start_at', 'am_target_completion_at'].forEach((id) => {
    const el = document.getElementById(id);
    el && el.addEventListener('change', checkVacationConflict);
  });

  // Filters the Assignee dropdown down to people assigned to the selected
  // Project (via each <option>'s data-projects attribute, set server-side in
  // includes/activity_modal.php from project_members) so picking a project
  // narrows a potentially long people list to just that project's team.
  // Picking "no project" (blank) shows everyone again — ad-hoc/unassigned
  // tasks aren't tied to any project's membership.
  //
  // `preserveCurrent` keeps whatever assignee is already selected visible even
  // if they're not a member of the newly-selected project, rather than hiding
  // it out from under them: used when fillForm() populates an existing task
  // (its saved assignee may predate a membership change, or the task may have
  // no project at all) so opening it for edit never looks like data went
  // missing. Live changes from the dropdown itself (and a fresh create) pass
  // `false` instead, so the filter is strict and an assignee left invalid by
  // the new project choice is swapped for the first valid one automatically.
  //
  // Admin-only variant: when the select has data-admin-assign="1" (set
  // server-side in includes/activity_modal.php via is_admin()), non-members
  // aren't hidden/disabled at all — instead they're moved into a separate,
  // visible "other people" <optgroup> (#am_assignee_group_other) so an admin
  // can still pick them directly from the dropdown. Saving with one of those
  // selected auto-joins them to the project server-side (see
  // auto_join_assignee_to_project() in api/activities.php) — updateAdminAssignHint()
  // below just surfaces that fact in the UI before saving.
  function updateAdminAssignHint() {
    const assigneeSelect = document.getElementById('am_assignee_id');
    const hint = document.getElementById('am_assignee_admin_hint');
    if (!assigneeSelect || !hint || assigneeSelect.dataset.adminAssign !== '1') return;
    const selectedOpt = assigneeSelect.options[assigneeSelect.selectedIndex];
    const inOtherGroup = !!(selectedOpt && selectedOpt.parentElement && selectedOpt.parentElement.id === 'am_assignee_group_other');
    hint.classList.toggle('d-none', !inOtherGroup);
  }

  function filterAssigneesByProject(preserveCurrent) {
    const projectSelect = document.getElementById('am_project_id');
    const assigneeSelect = document.getElementById('am_assignee_id');
    if (!projectSelect || !assigneeSelect) return;
    const projectId = projectSelect.value;
    const currentValue = assigneeSelect.value;
    const hint = document.getElementById('am_assignee_filter_hint');
    const isAdminAssign = assigneeSelect.dataset.adminAssign === '1';
    const teamGroup = isAdminAssign ? document.getElementById('am_assignee_group_team') : null;
    const otherGroup = isAdminAssign ? document.getElementById('am_assignee_group_other') : null;

    if (isAdminAssign && teamGroup && otherGroup) {
      // Regroup every option (wherever it currently lives) into "team" vs.
      // "other" rather than hiding anything, so an admin can always reach
      // every active person from this one dropdown.
      let otherCount = 0;
      Array.from(assigneeSelect.querySelectorAll('option')).forEach((opt) => {
        opt.hidden = false;
        opt.disabled = false;
        if (!projectId) {
          teamGroup.appendChild(opt);
          return;
        }
        const projects = (opt.dataset.projects || '').split(',').filter(Boolean);
        const isMember = projects.indexOf(String(projectId)) !== -1;
        if (isMember) {
          teamGroup.appendChild(opt);
        } else {
          otherGroup.appendChild(opt);
          otherCount++;
        }
      });
      otherGroup.style.display = projectId && otherCount > 0 ? '' : 'none';
      if (hint) hint.style.display = 'none';
      // Moving options between optgroups doesn't fire a change event, so the
      // previously-selected value is restored explicitly, then the hint text
      // is refreshed to match wherever that option ended up.
      assigneeSelect.value = currentValue;
      updateAdminAssignHint();
      return;
    }

    let anyHidden = false;
    Array.from(assigneeSelect.options).forEach((opt) => {
      if (!projectId || (preserveCurrent && opt.value === currentValue)) {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      const projects = (opt.dataset.projects || '').split(',').filter(Boolean);
      const isMember = projects.indexOf(String(projectId)) !== -1;
      opt.hidden = !isMember;
      opt.disabled = !isMember;
      if (!isMember) anyHidden = true;
    });
    if (hint) hint.style.display = projectId && anyHidden ? '' : 'none';
    if (projectId) {
      const selectedOpt = assigneeSelect.options[assigneeSelect.selectedIndex];
      if (selectedOpt && selectedOpt.hidden) {
        const firstVisible = Array.from(assigneeSelect.options).find((o) => !o.hidden);
        if (firstVisible) assigneeSelect.value = firstVisible.value;
      }
    }
  }
  const amProjectSelectEl = document.getElementById('am_project_id');
  amProjectSelectEl && amProjectSelectEl.addEventListener('change', function () { filterAssigneesByProject(false); });
  const amAssigneeSelectEl = document.getElementById('am_assignee_id');
  amAssigneeSelectEl && amAssigneeSelectEl.addEventListener('change', updateAdminAssignHint);

  function syncDatetimeTracking() {
    ['am_planned_start_at', 'am_target_completion_at'].forEach((id) => {
      const input = document.getElementById(id);
      if (input) input.dataset.lastDate = (input.value || '').slice(0, 10);
    });
  }

  function reset() {
    form.reset();
    setDescriptionHtml('');
    setNewCommentHtml('');
    currentId = null;
    document.getElementById('am_id').value = '';
    document.getElementById('activityModalTitle').textContent = 'New planned activity';
    document.getElementById('activityTabs').style.display = 'none';
    // Editing a task can leave a DIFFERENT tab (Time & Progress / Comments /
    // History) marked active if the user clicked through it while looking at
    // the task — Bootstrap's tab plugin only tracks that via .active/.show
    // classes on the nav-link and tab-pane, independent of the nav bar's own
    // display style above. The Details pane holds almost the entire create
    // form (title, project, assignee, dates, priority, tags, notes, etc.), so
    // if it's left inactive here, and the nav bar is hidden (as it is for a
    // brand-new task, with no way to click back to Details), the dialog
    // visibly shows just whatever few controls happened to live in that
    // other pane instead. Always force the tab state back to Details on reset.
    modalEl && modalEl.querySelectorAll('#activityTabs .nav-link').forEach((btn, i) => btn.classList.toggle('active', i === 0));
    modalEl && modalEl.querySelectorAll('.tab-pane').forEach((pane) => {
      const isDetails = pane.id === 'am_tab_details';
      pane.classList.toggle('active', isDetails);
      pane.classList.toggle('show', isDetails);
    });
    document.getElementById('am_vacation_warning').classList.add('d-none');
    document.getElementById('am_reclassify_block').classList.add('d-none');
    document.getElementById('am_interrupted_task_row').classList.add('d-none');
    document.getElementById('am_interrupted_task_name').textContent = '';
    document.getElementById('am_repeat_block').style.display = '';
    // Re-shown by fillForm() only when editing a task the user is allowed to delete/edit.
    document.getElementById('am_delete_btn').classList.add('d-none');
    document.getElementById('am_clone_btn').classList.add('d-none');
    document.getElementById('am_move_btn').classList.add('d-none');
    // Clear only the dynamically-populated content areas, not the tab panes that
    // contain them — am_tab_time also holds the static Status/Completion controls
    // (am_status, am_completion_pct), so wiping its innerHTML deleted those
    // permanently and broke every subsequent fillForm() call.
    document.getElementById('am_time_totals').textContent = '';
    ['am_time_entries', 'am_comments', 'am_history', 'am_interruptions_list'].forEach((id) => { document.getElementById(id).innerHTML = ''; });
    syncDatetimeTracking();
  }

  function loadParentOptions(excludeId, selectedId) {
    const select = document.getElementById('am_parent_activity_id');
    afFetch(API() + '?action=list&limit=100')
      .then((res) => {
        const options = (res.activities || []).filter((a) => String(a.id) !== String(excludeId));
        select.innerHTML = '<option value="">None — top-level task</option>' +
          options.map((a) => `<option value="${a.id}">${afEscapeHtml(a.title)}</option>`).join('');
        if (selectedId) select.value = selectedId;
      })
      .catch(() => {});
  }

  function openCreate(defaults) {
    reset();
    defaults = defaults || {};
    if (defaults.project_id) document.getElementById('am_project_id').value = defaults.project_id;
    if (defaults.assignee_id) document.getElementById('am_assignee_id').value = defaults.assignee_id;
    if (defaults.planned_start_at) document.getElementById('am_planned_start_at').value = defaults.planned_start_at;
    if (window.AF_PERSON_ID) document.getElementById('am_requester_id').value = window.AF_PERSON_ID;
    syncDatetimeTracking();
    filterAssigneesByProject(false);
    loadParentOptions(null);
    checkVacationConflict();
    modal && modal.show();
  }

  function fillForm(a) {
    document.getElementById('am_id').value = a.id;
    document.getElementById('am_title').value = a.title || '';
    setDescriptionHtml(a.description || '');
    document.getElementById('am_project_id').value = a.project_id || '';
    document.getElementById('am_category_id').value = a.category_id || '';
    document.getElementById('am_assignee_id').value = a.assignee_id || '';
    filterAssigneesByProject(true);
    document.getElementById('am_requester_id').value = a.requester_id || '';
    document.getElementById('am_planned_start_at').value = (a.planned_start_at || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('am_target_completion_at').value = (a.target_completion_at || '').replace(' ', 'T').slice(0, 16);
    syncDatetimeTracking();
    // Server already computed this (activity_vacation_conflict() in the 'get'
    // action) from the task's saved dates, so show it immediately rather than
    // firing an extra round trip; the change listeners registered above take
    // over from here for any further edits made within this modal session.
    showVacationWarning(a.vacation_conflict, a.assignee_name);
    // estimated_minutes is stored in the database in minutes; the form field shows
    // and edits it in hours (supports fractions, e.g. 1.5h), converted at the boundary.
    document.getElementById('am_estimated_hours').value = a.estimated_minutes
      ? Math.round((a.estimated_minutes / 60) * 100) / 100
      : '';
    document.getElementById('am_priority').value = a.priority || 'normal';
    document.getElementById('am_request_channel').value = a.request_channel || '';
    document.getElementById('am_tags').value = (a.tags || []).join(', ');
    document.getElementById('am_is_milestone').checked = !!Number(a.is_milestone);
    document.getElementById('am_is_issue').checked = !!Number(a.is_issue);
    document.getElementById('am_notes').value = a.notes || '';
    document.getElementById('am_status').value = a.status;
    document.getElementById('am_completion_pct').value = a.completion_pct;
    document.getElementById('am_pct_label').textContent = a.completion_pct + '%';
    document.getElementById('am_current_type').innerHTML = a.activity_type === 'unplanned'
      ? '<span class="badge bg-orange">Unplanned</span>' : '<span class="badge bg-primary">Planned</span>';
    document.getElementById('am_reclassify_block').classList.remove('d-none');
    // list_interruptions_for_activity() (returned as a.interruptions by the
    // 'get' action) includes rows where this activity is EITHER side of an
    // interruption, so pick the one where it's the interrupter — i.e. the
    // record created when this very task was logged via Quick-add's
    // "Interrupted task" field — to show what it interrupted. Reclassifying
    // a task to/from unplanned doesn't create or remove this record, so it's
    // shown whenever one exists, not gated on the current activity_type.
    const interruptedTaskRow = document.getElementById('am_interrupted_task_row');
    const interruption = (a.interruptions || []).find((i) => String(i.interrupting_activity_id) === String(a.id) && i.interrupted_activity_id);
    if (interruption) {
      const interruptedLabel = interruption.interrupted_title || ('#' + interruption.interrupted_activity_id);
      // Clickable so a user looking at the unplanned task can jump straight
      // to the planned task it interrupted, instead of hunting it down
      // separately — reuses the same openEdit() the rest of the modal's
      // internal navigation (Interruptions tab, parent task links) already
      // relies on, so it swaps the dialog's content in place rather than
      // opening a second dialog.
      document.getElementById('am_interrupted_task_name').innerHTML =
        `<a href="#" onclick="event.preventDefault(); afActivities.openEdit(${interruption.interrupted_activity_id})">${afEscapeHtml(interruptedLabel)}</a>`;
      interruptedTaskRow.classList.remove('d-none');
    } else {
      interruptedTaskRow.classList.add('d-none');
    }

    // The Interruptions tab shows the other direction: unplanned tasks that
    // interrupted THIS one (i.e. this activity is the "victim" side,
    // interrupted_activity_id === a.id) — the mirror image of the row above.
    const interruptedByList = (a.interruptions || []).filter((i) => String(i.interrupted_activity_id) === String(a.id));
    document.getElementById('am_interruptions_list').innerHTML = interruptedByList.map((i) => `
      <div class="border rounded p-2 mb-2 small af-interruption-item" onclick="afActivities.openEdit(${i.interrupting_activity_id})">
        <div class="d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-lightning-charge-fill text-orange"></i> ${afEscapeHtml(i.interrupting_title || ('#' + i.interrupting_activity_id))}</strong>
          ${i.interrupting_assignee_name ? `<span class="text-muted">${afEscapeHtml(i.interrupting_assignee_name)}</span>` : ''}
        </div>
        ${i.started_at ? `<div class="text-muted">${afEscapeHtml(i.started_at)}${i.time_lost_minutes != null ? ' · ' + i.time_lost_minutes + ' min lost' : ''}</div>` : ''}
        ${i.notes ? `<div>${afEscapeHtml(i.notes)}</div>` : ''}
      </div>`).join('') || '<p class="text-muted small">No interruptions recorded.</p>';

    document.getElementById('am_repeat_block').style.display = 'none';
    // can_delete/can_edit are computed server-side (api/activities.php's 'get'
    // action) from the same permission rules enforced on the actual requests, so
    // button visibility can't drift out of sync with what's really allowed.
    document.getElementById('am_delete_btn').classList.toggle('d-none', !a.can_delete);
    document.getElementById('am_clone_btn').classList.toggle('d-none', !a.can_edit);
    document.getElementById('am_move_btn').classList.toggle('d-none', !a.can_edit);

    document.getElementById('am_time_totals').textContent =
      `Estimated: ${a.time_totals.estimated_minutes || 0} min · Actual logged: ${a.time_totals.actual_minutes || 0} min`;
    document.getElementById('am_time_entries').innerHTML = (a.time_entries || []).map((t) => `
      <div class="border rounded p-2 mb-1 small">
        <div class="d-flex justify-content-between"><strong>${afEscapeHtml(t.person_name)}</strong><span>${t.duration_minutes != null ? t.duration_minutes + ' min' : 'running…'}</span></div>
        <div class="text-muted">${t.started_at} ${t.ended_at ? '→ ' + t.ended_at : ''}</div>
        ${t.notes ? `<div>${afEscapeHtml(t.notes)}</div>` : ''}
      </div>`).join('') || '<p class="text-muted small">No time logged yet.</p>';

    currentComments = a.comments || [];
    renderComments();

    // h.changes is a list of short "Label: old → new" lines built server-side
    // (see describe_activity_history_changes() in includes/models/activities.php)
    // from the raw before/after values audit_log() already records — this is
    // what actually changed, not just the action name.
    document.getElementById('am_history').innerHTML = (a.history || []).map((h) => `
      <div class="mb-2">
        <div><span class="text-muted">${h.created_at}</span> — ${afEscapeHtml(h.actor_name || 'System')} <em>${h.action.replace(/_/g, ' ')}</em></div>
        ${(h.changes && h.changes.length) ? '<ul class="mb-0 ps-3 text-muted">' + h.changes.map((c) => `<li>${afEscapeHtml(c)}</li>`).join('') + '</ul>' : ''}
      </div>`
    ).join('') || '<p class="text-muted small">No history recorded.</p>';

    currentId = a.id;
    currentType = a.activity_type;
    document.getElementById('activityModalTitle').textContent = 'Edit activity';
    document.getElementById('activityTabs').style.display = '';
  }

  // Comments are rendered from currentComments (rather than re-fetching) so
  // cancelling an in-progress edit doesn't need a round trip to the server.
  // Any in-progress inline edit's Quill instance is tied to a DOM node that
  // renderComments() is about to destroy, so drop our reference to it here
  // too (avoids holding a dangling Quill instance after its editor div is
  // wiped from the page).
  let commentEditQuills = {};
  function renderComments() {
    commentEditQuills = {};
    document.getElementById('am_comments').innerHTML = currentComments.map((c) => {
      const isOwner = window.AF_USER_ID != null && String(c.author_id) === String(window.AF_USER_ID);
      const editedNote = c.updated_at && c.updated_at !== c.created_at
        ? ` <span class="text-muted small">(edited ${afEscapeHtml(c.updated_at)})</span>` : '';
      return `
      <div class="mb-2" id="am_comment_${c.id}">
        <div class="d-flex justify-content-between align-items-start">
          <div><strong>${afEscapeHtml(c.author_name)}</strong> <span class="text-muted small">${afEscapeHtml(c.created_at)}</span>${editedNote}</div>
          ${isOwner ? `<button type="button" class="btn btn-sm btn-link p-0" onclick="afActivities.editComment(${c.id})">Edit</button>` : ''}
        </div>
        <!-- c.body is sanitized on write via sanitize_html() in add_activity_comment()/
             update_activity_comment() -- safe to echo raw here, same convention used
             for activity descriptions. -->
        <div id="am_comment_body_${c.id}">${c.body}</div>
      </div>`;
    }).join('') || '<p class="text-muted small">No comments yet.</p>';
  }

  function editComment(id) {
    const c = currentComments.find((x) => String(x.id) === String(id));
    const bodyEl = document.getElementById('am_comment_body_' + id);
    if (!c || !bodyEl) return;
    bodyEl.innerHTML = `
      <textarea class="form-control form-control-sm mb-1" id="am_comment_edit_${id}" rows="2"></textarea>
      <button type="button" class="btn btn-sm btn-primary" onclick="afActivities.saveCommentEdit(${id})">Save</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="afActivities.cancelEditComment(${id})">Cancel</button>`;
    const textarea = document.getElementById('am_comment_edit_' + id);
    textarea.value = c.body; // set as a value, not interpolated into the HTML string, so the raw text can't be mistaken for markup
    // This textarea is created fresh on every editComment() call, so it's safe
    // to enhance with a brand-new Quill instance each time (unlike the shared
    // description/new-comment fields, there's no stale-instance risk here).
    // It isn't nested in a <form>, so afInitRichText()'s submit-time sync is a
    // no-op for it -- fine, since saveCommentEdit() reads textarea.value
    // directly rather than via FormData/a submit event.
    commentEditQuills[id] = window.afInitRichText && window.afInitRichText('am_comment_edit_' + id);
    if (commentEditQuills[id]) commentEditQuills[id].focus(); else textarea.focus();
  }

  function cancelEditComment(id) {
    delete commentEditQuills[id];
    renderComments();
  }

  function saveCommentEdit(id) {
    const textarea = document.getElementById('am_comment_edit_' + id);
    if (!textarea) return;
    const body = textarea.value;
    const stripped = body.replace(/<[^>]*>/g, '').trim();
    if (!stripped) { afToast('Comment cannot be empty.', 'danger'); return; }
    afFetch(API(), { method: 'POST', body: { action: 'edit_comment', id, body } })
      .then((res) => {
        delete commentEditQuills[id];
        currentComments = res.comments || [];
        renderComments();
        afToast('Comment updated.');
      })
      .catch((err) => afToast(err.message, 'danger'));
  }

  function openEdit(id) {
    reset();
    afFetch(API() + '?action=get&id=' + id)
      .then((res) => {
        fillForm(res.activity);
        loadParentOptions(id, res.activity.parent_activity_id);
        modal && modal.show();
      })
      .catch((err) => afToast(err.message, 'danger'));
  }

  function save() {
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_milestone = form.querySelector('[name=is_milestone]').checked ? 1 : 0;
    data.is_issue = form.querySelector('[name=is_issue]').checked ? 1 : 0;
    data.tags = data.tags ? data.tags.split(',').map((t) => t.trim()).filter(Boolean) : [];
    // The form collects estimated effort in hours (as a float, e.g. 1.5) but the API/DB
    // store it as whole minutes — convert at the boundary and drop the hours field so it
    // isn't sent to the server under a name it doesn't recognize.
    const hours = parseFloat(data.estimated_hours);
    data.estimated_minutes = data.estimated_hours !== '' && !isNaN(hours) ? Math.round(hours * 60) : '';
    delete data.estimated_hours;
    const isEdit = !!data.id;
    const action = isEdit ? 'update' : 'create_planned';
    afFetch(API(), { method: 'POST', body: Object.assign({ action }, data) })
      .then((res) => {
        if (data.tags.length) {
          afFetch(API(), { method: 'POST', body: { action: 'set_tags', id: res.activity.id, tags: data.tags } });
        }
        afToast('Activity saved.');
        modal && modal.hide();
        document.dispatchEvent(new CustomEvent('af:activity-created'));
        if (window.afOnActivityCreated) { window.afOnActivityCreated(); } else { setTimeout(() => location.reload(), 400); }
      })
      .catch((err) => afToast(err.message, 'danger'));
  }

  function updateStatus() {
    if (!currentId) return;
    afFetch(API(), { method: 'POST', body: { action: 'update_status', id: currentId, status: document.getElementById('am_status').value } })
      .then(() => afToast('Status updated.'))
      .catch((err) => afToast(err.message, 'danger'));
  }

  function updateProgress() {
    if (!currentId) return;
    afFetch(API(), { method: 'POST', body: { action: 'update_progress', id: currentId, completion_pct: document.getElementById('am_completion_pct').value } })
      .then(() => afToast('Progress updated.'))
      .catch((err) => afToast(err.message, 'danger'));
  }

  function startTimer() {
    if (!currentId) { afToast('Save the activity first.', 'danger'); return; }
    afFetch(window.AF_BASE_URL + 'api/timer.php', { method: 'POST', body: { action: 'start', activity_id: currentId } })
      .then(() => afToast('Timer started.'))
      .catch((err) => afToast(err.message, 'danger'));
  }

  function stopTimer() {
    afFetch(window.AF_BASE_URL + 'api/timer.php', { method: 'POST', body: { action: 'stop' } })
      .then((res) => afToast('Logged ' + res.duration_minutes + ' minutes.'))
      .catch((err) => afToast(err.message, 'danger'));
  }

  function reclassify() {
    const newType = currentType === 'planned' ? 'unplanned' : 'planned';
    const reason = window.prompt(`Reclassify this activity as ${newType}? Please provide a reason (required, kept in the audit history):`);
    if (!reason) return;
    afFetch(API(), { method: 'POST', body: { action: 'reclassify', id: currentId, new_type: newType, reason } })
      .then(() => { afToast('Activity reclassified.'); openEdit(currentId); })
      .catch((err) => afToast(err.message, 'danger'));
  }

  function deleteActivity() {
    if (!currentId) return;
    const confirmed = afConfirm(
      'Delete this task? This will permanently remove it, along with all of its comments, ' +
      'time entries, and history. This cannot be undone.'
    );
    if (!confirmed) return;
    const deletingId = currentId;
    afFetch(API(), { method: 'POST', body: { action: 'delete', id: deletingId } })
      .then(() => {
        afToast('Task deleted.');
        modal && modal.hide();
        document.dispatchEvent(new CustomEvent('af:activity-deleted', { detail: { id: deletingId } }));
        // Reuses whatever refresh strategy the host page already wired up for
        // save() (full reload on most pages, calendar.refetchEvents() on the
        // calendar) — deleting a task needs the same "make the list match the
        // server again" refresh as creating or editing one does.
        if (window.afOnActivityCreated) { window.afOnActivityCreated(); } else { setTimeout(() => location.reload(), 400); }
      })
      .catch((err) => afToast(err.message, 'danger'));
  }

  // Shared by the single-task Clone…/Move… buttons in the Edit Activity dialog
  // (ids defaults to the task currently open) and by bulk "Clone selected" /
  // "Move selected" toolbars on list pages (which pass an explicit array).
  function openMoveOrClone(mode, ids) {
    ids = ids && ids.length ? ids : (currentId ? [currentId] : []);
    if (!ids.length) return;
    if (!moveCloneModal) { afToast('Unable to open the move/clone dialog.', 'danger'); return; }
    moveCloneMode = mode;
    moveCloneIds = ids;
    document.getElementById('taskMoveCloneTitle').textContent = mode === 'clone'
      ? (ids.length > 1 ? `Clone ${ids.length} tasks` : 'Clone task')
      : (ids.length > 1 ? `Move ${ids.length} tasks` : 'Move task');
    document.getElementById('taskMoveCloneSummary').textContent = mode === 'clone'
      ? `Choose a project to create ${ids.length > 1 ? 'copies of the selected tasks' : 'a copy of this task'} in.`
      : `Choose a project to move ${ids.length > 1 ? 'the selected tasks' : 'this task'} to. Comments and time entries move with ${ids.length > 1 ? 'them' : 'it'}.`;
    document.getElementById('taskMoveCloneNote').textContent = mode === 'clone'
      ? 'The copy starts fresh — no comments, time entries, or history carry over.'
      : '';
    document.getElementById('taskMoveCloneConfirmBtn').textContent = mode === 'clone' ? 'Clone' : 'Move';
    // Modal-in-modal: hide the task editor first so only one is visible/focused at a time.
    modal && modal.hide();
    moveCloneModal.show();
  }

  function confirmMoveOrClone() {
    const select = document.getElementById('taskMoveCloneProject');
    const projectId = select ? select.value : '';
    if (!projectId) { afToast('Choose a destination project.', 'danger'); return; }
    afFetch(API(), { method: 'POST', body: { action: moveCloneMode, ids: moveCloneIds, project_id: projectId } })
      .then(() => {
        afToast(moveCloneMode === 'clone'
          ? (moveCloneIds.length > 1 ? 'Tasks cloned.' : 'Task cloned.')
          : (moveCloneIds.length > 1 ? 'Tasks moved.' : 'Task moved.'));
        moveCloneModal && moveCloneModal.hide();
        document.dispatchEvent(new CustomEvent('af:activity-created'));
        if (window.afOnActivityCreated) { window.afOnActivityCreated(); } else { setTimeout(() => location.reload(), 400); }
      })
      .catch((err) => afToast(err.message, 'danger'));
  }

  // NOTE: these are bound directly to their form elements (rather than via a
  // document-level delegated 'submit' listener keyed off e.target.id, as this
  // used to be written) because the global loading-overlay listener in app.js
  // also listens for 'submit' on document. Listeners on the same node+phase
  // run in registration order, and app.js's listener is registered first (it
  // loads before this file) — so a document-level listener here would always
  // have its e.preventDefault() call land ONE TICK TOO LATE for app.js's
  // check of e.defaultPrevented, making app.js think this was an
  // un-intercepted, page-navigating form submit and show the loading overlay
  // with no matching hide (it's actually intercepted right here). Binding
  // directly to the form element itself makes this handler run in the
  // "target phase", before the event ever bubbles up to document, so
  // preventDefault() always takes effect in time. These two forms are static
  // markup in includes/activity_modal.php (never destroyed/recreated), so a
  // one-time binding here is safe.
  const manualTimeFormEl = document.getElementById('manualTimeForm');
  manualTimeFormEl && manualTimeFormEl.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!currentId) return;
    const data = Object.fromEntries(new FormData(manualTimeFormEl).entries());
    afFetch(window.AF_BASE_URL + 'api/time_entries.php', { method: 'POST', body: Object.assign({ action: 'manual', activity_id: currentId }, data) })
      .then(() => { afToast('Time entry added.'); openEdit(currentId); })
      .catch((err) => afToast(err.message, 'danger'));
  });

  const commentFormEl = document.getElementById('commentForm');
  commentFormEl && commentFormEl.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!currentId) return;
    const data = Object.fromEntries(new FormData(commentFormEl).entries());
    const stripped = (data.body || '').replace(/<[^>]*>/g, '').trim();
    if (!stripped) { afToast('Comment cannot be empty.', 'danger'); return; }
    afFetch(API(), { method: 'POST', body: { action: 'add_comment', id: currentId, body: data.body } })
      .then(() => { setNewCommentHtml(''); openEdit(currentId); })
      .catch((err) => afToast(err.message, 'danger'));
  });

  return {
    openCreate, openEdit, save, updateStatus, updateProgress, startTimer, stopTimer, reclassify,
    editComment, cancelEditComment, saveCommentEdit, deleteActivity,
    openMoveOrClone, confirmMoveOrClone,
  };
})();

// Reusable bulk task-selection toolbar for table-based task lists (My Tasks,
// Team Activities). Expects a first-column "select all" checkbox with
// id="af-select-all", per-row checkboxes with class="af-task-select" (their
// value being the task id), and a toolbar with id="af-bulk-bar" containing
// #af-bulk-count, #af-bulk-clone, and #af-bulk-move. Pages that don't have
// these elements simply get a no-op — safe to call unconditionally.
window.afInitBulkTaskSelect = function () {
  const bar = document.getElementById('af-bulk-bar');
  const countEl = document.getElementById('af-bulk-count');
  const selectAll = document.getElementById('af-select-all');
  const cloneBtn = document.getElementById('af-bulk-clone');
  const moveBtn = document.getElementById('af-bulk-move');
  if (!bar || !countEl) return;

  function checkboxes() { return Array.from(document.querySelectorAll('.af-task-select')); }
  function selectedIds() { return checkboxes().filter((c) => c.checked).map((c) => parseInt(c.value, 10)); }

  function refresh() {
    const all = checkboxes();
    const ids = selectedIds();
    bar.classList.toggle('d-none', ids.length === 0);
    bar.classList.toggle('d-flex', ids.length > 0);
    countEl.textContent = ids.length ? `${ids.length} task${ids.length === 1 ? '' : 's'} selected` : '';
    if (selectAll) {
      selectAll.checked = all.length > 0 && ids.length === all.length;
      selectAll.indeterminate = ids.length > 0 && ids.length < all.length;
    }
  }

  document.addEventListener('change', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('af-task-select')) refresh();
  });
  selectAll && selectAll.addEventListener('change', function () {
    checkboxes().forEach((c) => { c.checked = selectAll.checked; });
    refresh();
  });
  cloneBtn && cloneBtn.addEventListener('click', function () {
    const ids = selectedIds();
    if (ids.length) window.afActivities.openMoveOrClone('clone', ids);
  });
  moveBtn && moveBtn.addEventListener('click', function () {
    const ids = selectedIds();
    if (ids.length) window.afActivities.openMoveOrClone('move', ids);
  });

  refresh();
};

// Shared activity create/edit modal logic.
window.afActivities = (function () {
  const modalEl = document.getElementById('activityModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('activityForm');
  let currentId = null;
  let currentType = 'planned';

  const API = () => window.AF_BASE_URL + 'api/activities.php';

  function reset() {
    form.reset();
    currentId = null;
    document.getElementById('am_id').value = '';
    document.getElementById('activityModalTitle').textContent = 'New planned activity';
    document.getElementById('activityTabs').style.display = 'none';
    document.getElementById('am_reclassify_block').classList.add('d-none');
    document.getElementById('am_repeat_block').style.display = '';
    ['am_tab_time', 'am_tab_comments', 'am_tab_history'].forEach((id) => { document.getElementById(id).innerHTML = ''; });
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
    loadParentOptions(null);
    modal && modal.show();
  }

  function fillForm(a) {
    document.getElementById('am_id').value = a.id;
    document.getElementById('am_title').value = a.title || '';
    document.getElementById('am_description').value = a.description || '';
    document.getElementById('am_project_id').value = a.project_id || '';
    document.getElementById('am_category_id').value = a.category_id || '';
    document.getElementById('am_assignee_id').value = a.assignee_id || '';
    document.getElementById('am_requester_id').value = a.requester_id || '';
    document.getElementById('am_planned_start_at').value = (a.planned_start_at || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('am_target_completion_at').value = (a.target_completion_at || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('am_estimated_minutes').value = a.estimated_minutes || '';
    document.getElementById('am_priority').value = a.priority || 'normal';
    document.getElementById('am_request_channel').value = a.request_channel || '';
    document.getElementById('am_tags').value = (a.tags || []).join(', ');
    document.getElementById('am_is_milestone').checked = !!Number(a.is_milestone);
    document.getElementById('am_notes').value = a.notes || '';
    document.getElementById('am_status').value = a.status;
    document.getElementById('am_completion_pct').value = a.completion_pct;
    document.getElementById('am_pct_label').textContent = a.completion_pct + '%';
    document.getElementById('am_current_type').innerHTML = a.activity_type === 'unplanned'
      ? '<span class="badge bg-orange">Unplanned</span>' : '<span class="badge bg-primary">Planned</span>';
    document.getElementById('am_reclassify_block').classList.remove('d-none');
    document.getElementById('am_repeat_block').style.display = 'none';

    document.getElementById('am_time_totals').textContent =
      `Estimated: ${a.time_totals.estimated_minutes || 0} min · Actual logged: ${a.time_totals.actual_minutes || 0} min`;
    document.getElementById('am_time_entries').innerHTML = (a.time_entries || []).map((t) => `
      <div class="border rounded p-2 mb-1 small">
        <div class="d-flex justify-content-between"><strong>${afEscapeHtml(t.person_name)}</strong><span>${t.duration_minutes != null ? t.duration_minutes + ' min' : 'running…'}</span></div>
        <div class="text-muted">${t.started_at} ${t.ended_at ? '→ ' + t.ended_at : ''}</div>
        ${t.notes ? `<div>${afEscapeHtml(t.notes)}</div>` : ''}
      </div>`).join('') || '<p class="text-muted small">No time logged yet.</p>';

    document.getElementById('am_comments').innerHTML = (a.comments || []).map((c) => `
      <div class="mb-2"><strong>${afEscapeHtml(c.author_name)}</strong> <span class="text-muted small">${c.created_at}</span>
      <div>${afEscapeHtml(c.body)}</div></div>`).join('') || '<p class="text-muted small">No comments yet.</p>';

    document.getElementById('am_history').innerHTML = (a.history || []).map((h) => `
      <div class="mb-1"><span class="text-muted">${h.created_at}</span> — ${afEscapeHtml(h.actor_name || 'System')} <em>${h.action.replace(/_/g, ' ')}</em></div>`
    ).join('') || '<p class="text-muted small">No history recorded.</p>';

    currentId = a.id;
    currentType = a.activity_type;
    document.getElementById('activityModalTitle').textContent = 'Edit activity';
    document.getElementById('activityTabs').style.display = '';
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
    data.tags = data.tags ? data.tags.split(',').map((t) => t.trim()).filter(Boolean) : [];
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

  document.addEventListener('submit', function (e) {
    if (e.target && e.target.id === 'manualTimeForm') {
      e.preventDefault();
      if (!currentId) return;
      const data = Object.fromEntries(new FormData(e.target).entries());
      afFetch(window.AF_BASE_URL + 'api/time_entries.php', { method: 'POST', body: Object.assign({ action: 'manual', activity_id: currentId }, data) })
        .then(() => { afToast('Time entry added.'); openEdit(currentId); })
        .catch((err) => afToast(err.message, 'danger'));
    }
    if (e.target && e.target.id === 'commentForm') {
      e.preventDefault();
      if (!currentId) return;
      const data = Object.fromEntries(new FormData(e.target).entries());
      afFetch(API(), { method: 'POST', body: { action: 'add_comment', id: currentId, body: data.body } })
        .then(() => { e.target.reset(); openEdit(currentId); })
        .catch((err) => afToast(err.message, 'danger'));
    }
  });

  return { openCreate, openEdit, save, updateStatus, updateProgress, startTimer, stopTimer, reclassify };
})();

// Workload comparison page: for a chosen roster of people (or everyone),
// how many tasks (matching the chosen statuses and overlapping the chosen
// date range) are on their plate right now, regardless of project — so a
// manager can spot who's free before assigning new work.
(function () {
  const formEl = document.getElementById('workloadFilterForm');
  if (!formEl) return;

  const i18n = window.AF_I18N_WORKLOAD || {};
  const globalI18n = window.AF_I18N || {};

  // Same "All / individual picks" checkbox-dropdown behavior used by the Task
  // Board and Vacations pages: checking "All" clears individual picks,
  // checking any individual pick clears "All", and unchecking the last
  // individual pick reverts back to "All" (never an ambiguous nothing-picked
  // state).
  function wireMultiSelectDropdown(allId, itemSelector, labelId, allText, singular, plural) {
    const allCb = document.getElementById(allId);
    const itemCbs = Array.from(document.querySelectorAll(itemSelector));
    function updateLabel() {
      const label = document.getElementById(labelId);
      if (!label) return;
      const checked = itemCbs.filter((cb) => cb.checked);
      if (!checked.length) { label.textContent = allText; return; }
      const noun = checked.length === 1 ? singular : plural;
      const suffix = globalI18n.board_selected_suffix || '{count} {noun} selected';
      label.textContent = suffix.replace('{count}', checked.length).replace('{noun}', noun);
    }
    function selectedValues() { return itemCbs.filter((cb) => cb.checked).map((cb) => cb.value); }
    if (allCb) {
      allCb.addEventListener('change', function () {
        if (allCb.checked) itemCbs.forEach((cb) => { cb.checked = false; });
        updateLabel();
      });
    }
    itemCbs.forEach((cb) => {
      cb.addEventListener('change', function () {
        if (cb.checked && allCb) allCb.checked = false;
        if (allCb && !itemCbs.some((c) => c.checked)) allCb.checked = true;
        updateLabel();
      });
    });
    updateLabel();
    return { selectedValues };
  }

  const peopleGroup = wireMultiSelectDropdown(
    'wlPeopleAll', '.wl-person-checkbox', 'wlPeopleLabel',
    globalI18n.board_all_team_members || 'All team members',
    globalI18n.board_member_singular || 'member', globalI18n.board_member_plural || 'members'
  );
  const statusGroup = wireMultiSelectDropdown(
    'wlStatusAll', '.wl-status-checkbox', 'wlStatusLabel',
    globalI18n.board_all_statuses || 'All statuses',
    globalI18n.board_status_singular || 'status', globalI18n.board_status_plural || 'statuses'
  );

  function taskRowHtml(t) {
    const projectLabel = t.project_name ? afEscapeHtml(t.project_name) : `<span class="text-muted">${afEscapeHtml(i18n.noProject || 'No project')}</span>`;
    return `
      <div class="d-flex justify-content-between align-items-center border-top py-2">
        <div>
          <div class="fw-semibold">${afEscapeHtml(t.title)}</div>
          <div class="small text-muted">${projectLabel} · <span class="badge ${statusBadgeClass(t.status)}">${afEscapeHtml(statusLabelFor(t.status))}</span></div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="afActivities.openEdit(${t.id})">${afEscapeHtml(i18n.open || 'Open')}</button>
      </div>`;
  }

  // Status labels/classes aren't known client-side (they're admin-managed),
  // so read them straight off the matching checkbox's <label> text and reuse
  // Bootstrap's generic badge coloring rather than duplicating the
  // slug -> color/label mapping that only PHP's status_badge_class()/
  // task_status_label() know about.
  function statusLabelFor(slug) {
    const cb = document.getElementById('wlStatus' + slug);
    const label = cb && cb.closest('.form-check') && cb.closest('.form-check').querySelector('label');
    return label ? label.textContent : slug;
  }
  function statusBadgeClass() { return 'bg-secondary'; }

  function personCardHtml(person) {
    const tasksHtml = person.tasks.length
      ? person.tasks.map(taskRowHtml).join('')
      : `<div class="text-muted small border-top pt-2">${afEscapeHtml(i18n.noTasks || 'No matching tasks in this range.')}</div>`;
    const noun = person.task_count === 1 ? (i18n.taskSingular || 'task') : (i18n.taskPlural || 'tasks');
    const badgeClass = person.task_count === 0 ? 'bg-success' : (person.task_count >= 6 ? 'bg-danger' : 'bg-primary');
    return `
      <div class="af-card mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold">${afEscapeHtml(person.person_name)}</div>
            ${person.job_title ? `<div class="small text-muted">${afEscapeHtml(person.job_title)}</div>` : ''}
          </div>
          <span class="badge ${badgeClass} fs-6">${person.task_count} ${afEscapeHtml(noun)}</span>
        </div>
        ${tasksHtml}
      </div>`;
  }

  function render(results) {
    const container = document.getElementById('workloadResults');
    const emptyEl = document.getElementById('workloadEmpty');
    if (!results.length) {
      container.innerHTML = '';
      emptyEl.classList.remove('d-none');
      return;
    }
    emptyEl.classList.add('d-none');
    container.innerHTML = results.map(personCardHtml).join('');
  }

  function runQuery() {
    const params = new URLSearchParams({
      action: 'summary',
      date_from: document.getElementById('wlDateFrom').value,
      date_to: document.getElementById('wlDateTo').value,
      order: document.getElementById('wlSortOrder').value,
    });
    peopleGroup.selectedValues().forEach((id) => params.append('person_id[]', id));
    statusGroup.selectedValues().forEach((slug) => params.append('status[]', slug));
    window.afLoadingShow && window.afLoadingShow();
    fetch(window.AF_BASE_URL + 'api/workload.php?' + params.toString(), { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => {
        if (!res.ok) { afToast(res.error || 'Unable to load workload.', 'danger'); return; }
        render(res.results || []);
      })
      .catch(() => afToast('Unable to load workload.', 'danger'))
      .finally(() => window.afLoadingHide && window.afLoadingHide());
  }

  formEl.addEventListener('submit', function (e) {
    e.preventDefault();
    runQuery();
  });

  // Refresh the results after editing a task from within this page's own
  // list (e.g. reassigning it), same hook vacations.js uses for its
  // conflicts list.
  window.afOnActivityCreated = function () { runQuery(); };

  runQuery();
})();

(function () {
  const el = document.getElementById('calendarEl');
  if (!el || typeof FullCalendar === 'undefined') return;

  const i18n = window.AF_I18N || {};

  // Shared wiring for the "All X" / multi-select checkbox dropdowns (mirrors
  // the same pattern on the Task Board's member/status filters): checking
  // "All" clears individual picks, checking any individual pick clears
  // "All", and unchecking the last individual pick reverts to "All" so the
  // filter can never end up in an ambiguous nothing-selected state. Unlike
  // the Task Board (which re-submits a <form> and reloads the page), this
  // calls back into FullCalendar's own refetchEvents() instead.
  function wireMultiSelectDropdown(allId, itemSelector, labelId, allText, singular, plural, onChange) {
    const allCb = document.getElementById(allId);
    const itemCbs = Array.from(document.querySelectorAll(itemSelector));
    function updateLabel() {
      const label = document.getElementById(labelId);
      if (!label) return;
      const checked = itemCbs.filter((cb) => cb.checked);
      if (!checked.length) { label.textContent = allText; return; }
      const noun = checked.length === 1 ? singular : plural;
      const suffix = i18n.board_selected_suffix || '{count} {noun} selected';
      label.textContent = suffix.replace('{count}', checked.length).replace('{noun}', noun);
    }
    function selectedValues() { return itemCbs.filter((cb) => cb.checked).map((cb) => cb.value); }
    if (allCb) {
      allCb.addEventListener('change', function () {
        if (allCb.checked) itemCbs.forEach((cb) => { cb.checked = false; });
        updateLabel();
        onChange();
      });
    }
    itemCbs.forEach((cb) => {
      cb.addEventListener('change', function () {
        if (cb.checked && allCb) allCb.checked = false;
        if (allCb && !itemCbs.some((c) => c.checked)) allCb.checked = true;
        updateLabel();
        onChange();
      });
    });
    updateLabel();
    return { selectedValues };
  }

  const assigneeGroup = wireMultiSelectDropdown(
    'calAssigneeAll', '.cal-assignee-checkbox', 'calAssigneeLabel',
    i18n.calendar_all_employees || 'All employees',
    i18n.calendar_employee_singular || 'employee', i18n.calendar_employee_plural || 'employees',
    () => calendar.refetchEvents()
  );
  const projectGroup = wireMultiSelectDropdown(
    'calProjectAll', '.cal-project-checkbox', 'calProjectLabel',
    i18n.calendar_all_projects || 'All projects',
    i18n.calendar_project_singular || 'project', i18n.calendar_project_plural || 'projects',
    () => calendar.refetchEvents()
  );

  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'timeGridWeek',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    height: 'auto',
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    nowIndicator: true,
    events: function (info, success, failure) {
      const params = new URLSearchParams({ action: 'events', start: info.startStr, end: info.endStr });
      assigneeGroup.selectedValues().forEach((id) => params.append('assignee_id[]', id));
      projectGroup.selectedValues().forEach((id) => params.append('project_id[]', id));
      window.afLoadingShow();
      fetch(window.AF_BASE_URL + 'api/calendar.php?' + params.toString())
        .then((r) => r.json()).then(success).catch(failure)
        .finally(() => window.afLoadingHide());
    },
    eventClick: function (info) {
      afActivities.openEdit(info.event.id);
    },
    eventDrop: function (info) { saveReschedule(info); },
    eventResize: function (info) { saveReschedule(info); },
  });

  function toLocalSql(d) {
    if (!d) return null;
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  function saveReschedule(info) {
    const ev = info.event;
    afFetch(window.AF_BASE_URL + 'api/activities.php', {
      method: 'POST',
      body: {
        action: 'reschedule',
        id: ev.id,
        planned_start_at: toLocalSql(ev.start),
        target_completion_at: toLocalSql(ev.end),
      },
    }).then(() => afToast('Schedule updated.'))
      .catch((err) => { afToast(err.message, 'danger'); info.revert(); });
  }

  calendar.render();
  window.afOnActivityCreated = function () { calendar.refetchEvents(); };
})();

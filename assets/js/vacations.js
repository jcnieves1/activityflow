(function () {
  const calEl = document.getElementById('vacationsCalendarEl');
  if (!calEl || typeof FullCalendar === 'undefined') return;

  const i18n = window.AF_I18N_VACATIONS || {};
  const modalEl = document.getElementById('vacationModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('vacationForm');
  const personField = document.getElementById('vac_person_id');
  const personFieldIsSelect = personField.tagName === 'SELECT';

  // A small fixed palette, indexed by person id, so the same person always
  // gets the same color everywhere it's shown (calendar events, the filter
  // dropdown's swatches, and the conflicts list below) without needing a
  // color column on the people table.
  const PALETTE = ['#4361ee', '#e63946', '#2a9d8f', '#f4a261', '#8e44ad', '#e76f51', '#457b9d', '#ff006e', '#06d6a0', '#ffb703', '#6a4c93', '#c9184a'];
  function colorForPerson(id) {
    const n = parseInt(id, 10) || 0;
    return PALETTE[n % PALETTE.length];
  }
  document.querySelectorAll('.af-person-swatch').forEach((el) => {
    el.style.background = colorForPerson(el.dataset.personId);
  });

  // Same "All / individual picks" checkbox-dropdown behavior as the
  // Calendar and Task Board filters: checking "All" clears individual
  // picks, checking any individual pick clears "All", and unchecking the
  // last individual pick reverts to "All" (never an ambiguous nothing-picked
  // state).
  function wireMultiSelectDropdown(allId, itemSelector, labelId, allText, singular, plural, onChange) {
    const allCb = document.getElementById(allId);
    const itemCbs = Array.from(document.querySelectorAll(itemSelector));
    function updateLabel() {
      const label = document.getElementById(labelId);
      if (!label) return;
      const checked = itemCbs.filter((cb) => cb.checked);
      if (!checked.length) { label.textContent = allText; return; }
      const noun = checked.length === 1 ? singular : plural;
      const suffix = (window.AF_I18N && window.AF_I18N.board_selected_suffix) || '{count} {noun} selected';
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

  const personGroup = wireMultiSelectDropdown(
    'vacPersonAll', '.vac-person-checkbox', 'vacPersonLabel',
    i18n.allPeople || 'All people', i18n.personSingular || 'person', i18n.personPlural || 'people',
    () => { calendar.refetchEvents(); loadConflicts(); }
  );

  // FullCalendar's all-day event end date is EXCLUSIVE (the day after the
  // last day shown), but vacations.end_date is INCLUSIVE (the last day off)
  // — add one day when building the event so the last day actually renders.
  function nextDay(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    const pad = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  const calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,multiMonthYear' },
    buttonText: { today: i18n.today || 'Today', dayGridMonth: i18n.viewMonth || 'Month', multiMonthYear: i18n.viewYear || 'Year' },
    height: 'auto',
    events: function (info, success, failure) {
      const params = new URLSearchParams({ action: 'list', start: info.startStr, end: info.endStr });
      personGroup.selectedValues().forEach((id) => params.append('person_id[]', id));
      window.afLoadingShow();
      fetch(window.AF_BASE_URL + 'api/vacations.php?' + params.toString())
        .then((r) => r.json())
        .then((res) => {
          const events = (res.vacations || []).map((v) => ({
            id: v.id,
            title: v.person_name,
            start: v.start_date,
            end: nextDay(v.end_date),
            allDay: true,
            backgroundColor: colorForPerson(v.person_id),
            borderColor: colorForPerson(v.person_id),
            extendedProps: v,
          }));
          success(events);
        })
        .catch(failure)
        .finally(() => window.afLoadingHide());
    },
    eventClick: function (info) {
      openVacation(info.event.extendedProps);
    },
  });
  calendar.render();

  function setFieldsDisabled(disabled) {
    ['vac_start_date', 'vac_end_date', 'vac_notes'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = disabled;
    });
  }

  function openCreate() {
    form.reset();
    document.getElementById('vac_id').value = '';
    document.getElementById('vacationModalTitle').textContent = i18n.newTitle || 'New vacation';
    setFieldsDisabled(false);
    if (personFieldIsSelect) personField.disabled = false;
    document.getElementById('vac_delete_btn').classList.add('d-none');
    document.getElementById('vac_save_btn').classList.remove('d-none');
    modal.show();
  }

  // Opened from clicking a vacation event on the calendar. The person a
  // vacation belongs to can never be reassigned (see update_vacation()
  // server-side, which ignores any submitted person_id on an existing row),
  // so the person select — for admins who have one — is always disabled
  // here, even though it's still enabled when creating a brand-new entry.
  function openVacation(v) {
    document.getElementById('vac_id').value = v.id;
    if (personFieldIsSelect) { personField.value = v.person_id; personField.disabled = true; }
    document.getElementById('vac_start_date').value = v.start_date;
    document.getElementById('vac_end_date').value = v.end_date;
    document.getElementById('vac_notes').value = v.notes || '';
    document.getElementById('vacationModalTitle').textContent = v.person_name;
    const canManage = !!v.can_manage;
    setFieldsDisabled(!canManage);
    document.getElementById('vac_delete_btn').classList.toggle('d-none', !canManage);
    document.getElementById('vac_save_btn').classList.toggle('d-none', !canManage);
    modal.show();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    const action = data.id ? 'update' : 'create';
    afFetch(window.AF_BASE_URL + 'api/vacations.php', { method: 'POST', body: Object.assign({ action }, data) })
      .then(() => {
        afToast('Vacation saved.');
        modal.hide();
        calendar.refetchEvents();
        loadConflicts();
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  document.getElementById('vac_delete_btn').addEventListener('click', function () {
    const id = document.getElementById('vac_id').value;
    if (!id) return;
    if (!afConfirm(i18n.deleteConfirm || 'Delete this vacation entry? This cannot be undone.')) return;
    afFetch(window.AF_BASE_URL + 'api/vacations.php', { method: 'POST', body: { action: 'delete', id } })
      .then(() => {
        afToast('Vacation deleted.');
        modal.hide();
        calendar.refetchEvents();
        loadConflicts();
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  function renderConflicts(rows) {
    const tbody = document.getElementById('vacationConflictsBody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6"><div class="af-empty"><i class="bi bi-check2-circle"></i>' +
        afEscapeHtml(i18n.noConflicts || 'No conflicts.') + '</div></td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((r) => {
      const taskStart = r.planned_start_at || r.actual_start_at || '';
      const taskEnd = r.target_completion_at || r.actual_completion_at || '';
      return '<tr>' +
        '<td><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:' + colorForPerson(r.person_id) + '"></span>' + afEscapeHtml(r.person_name) + '</td>' +
        '<td>' + afEscapeHtml(r.vacation_start) + ' – ' + afEscapeHtml(r.vacation_end) + '</td>' +
        '<td>' + afEscapeHtml(r.title) + '</td>' +
        '<td class="small">' + afEscapeHtml(taskStart) + ' – ' + afEscapeHtml(taskEnd) + '</td>' +
        '<td>' + (r.project_name ? afEscapeHtml(r.project_name) : '<span class="text-muted">—</span>') + '</td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="afActivities.openEdit(' + r.activity_id + ')">' + afEscapeHtml(i18n.open || 'Open') + '</button></td>' +
        '</tr>';
    }).join('');
  }

  function loadConflicts() {
    const params = new URLSearchParams({ action: 'conflicts' });
    personGroup.selectedValues().forEach((id) => params.append('person_id[]', id));
    afFetch(window.AF_BASE_URL + 'api/vacations.php?' + params.toString())
      .then((res) => renderConflicts(res.conflicts || []))
      .catch(() => {});
  }
  loadConflicts();

  window.afVacations = { openCreate };
  // Reuse the shared activity modal's own post-save refresh hook so editing
  // a colliding task from this page's conflicts list (via afActivities.openEdit)
  // refreshes the conflicts list here too, exactly like it refreshes the
  // calendar on calendar.php.
  window.afOnActivityCreated = function () { loadConflicts(); };
})();

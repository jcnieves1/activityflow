(function () {
  const el = document.getElementById('calendarEl');
  if (!el || typeof FullCalendar === 'undefined') return;

  const assigneeSel = document.getElementById('calAssignee');
  const projectSel = document.getElementById('calProject');

  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'timeGridWeek',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    height: 'auto',
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    nowIndicator: true,
    events: function (info, success, failure) {
      const params = new URLSearchParams({
        action: 'events', start: info.startStr, end: info.endStr,
        assignee_id: assigneeSel.value, project_id: projectSel.value,
      });
      fetch(window.AF_BASE_URL + 'api/calendar.php?' + params.toString())
        .then((r) => r.json()).then(success).catch(failure);
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
  assigneeSel.addEventListener('change', () => calendar.refetchEvents());
  projectSel.addEventListener('change', () => calendar.refetchEvents());
  window.afOnActivityCreated = function () { calendar.refetchEvents(); };
})();

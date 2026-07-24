(function () {
  const P = window.AF_PROJECT;
  if (!P) return;

  window.afInitRichText && window.afInitRichText('editProjectDescription');

  // Wire up the form handlers first, unconditionally. Chart rendering below is
  // "nice to have" and talks to a third-party CDN script (Chart.js) that can fail
  // to load (ad blockers, firewalls, CDN hiccups). Previously the chart code ran
  // first in this same function, so a "Chart is not defined" error there aborted
  // the whole script and silently skipped attaching the Edit Project / Add Member
  // submit handlers below it — the forms then fell back to a native, unhandled
  // submission. Registering the handlers first means a chart failure can never
  // again take down project editing or member management.
  const memberForm = document.getElementById('memberForm');
  memberForm && memberForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(memberForm).entries());
    afFetch(window.AF_BASE_URL + 'api/projects.php', { method: 'POST', body: Object.assign({ action: 'add_member' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  const editProjectForm = document.getElementById('editProjectForm');
  editProjectForm && editProjectForm.addEventListener('submit', function (e) {
    e.preventDefault();
    try {
      // FormData omits a checkbox entirely when it's unchecked (rather than sending
      // "0"), so its presence/absence in `data` already tells us the checked state —
      // no need to re-query the DOM for it, which is one less thing that can fail.
      const data = Object.fromEntries(new FormData(editProjectForm).entries());
      data.is_archived = data.is_archived ? 1 : 0;
      data.member_ids = Array.from(editProjectForm.querySelectorAll('.project-member-checkbox:checked')).map((cb) => cb.value);
      afFetch(window.AF_BASE_URL + 'api/projects.php', { method: 'POST', body: Object.assign({ action: 'update' }, data) })
        .then(() => { afToast('Project updated.'); location.reload(); })
        .catch((err) => afToast(err.message, 'danger'));
    } catch (err) {
      afToast(err.message || 'Unable to save the project. Please try again.', 'danger');
    }
  });

  // Delete confirmation: the button stays disabled until the typed text exactly
  // matches the project name, so deletion (which cascades to every task, comment,
  // and time entry under the project, with no undo) requires a deliberate,
  // informed action rather than a single reflexive click.
  const deleteConfirmInput = document.getElementById('deleteProjectConfirmInput');
  const deleteConfirmBtn = document.getElementById('deleteProjectConfirmBtn');
  if (deleteConfirmInput && deleteConfirmBtn) {
    deleteConfirmInput.addEventListener('input', function () {
      deleteConfirmBtn.disabled = deleteConfirmInput.value !== P.name;
    });
  }

  window.afProjectDetail = {
    removeMember(personId) {
      if (!afConfirm('Remove this member from the project?')) return;
      afFetch(window.AF_BASE_URL + 'api/projects.php', { method: 'POST', body: { action: 'remove_member', project_id: P.id, person_id: personId } })
        .then(() => location.reload())
        .catch((err) => afToast(err.message, 'danger'));
    },

    deleteProject(id, name) {
      if (deleteConfirmInput && deleteConfirmInput.value !== name) return; // belt-and-braces alongside the disabled button
      deleteConfirmBtn.disabled = true;
      afFetch(window.AF_BASE_URL + 'api/projects.php', {
        method: 'POST',
        body: { action: 'delete', id, confirm_name: deleteConfirmInput ? deleteConfirmInput.value : name },
      })
        .then(() => {
          afToast('Project deleted.');
          setTimeout(() => { window.location.href = window.AF_BASE_URL + 'projects.php'; }, 400);
        })
        .catch((err) => {
          afToast(err.message, 'danger');
          if (deleteConfirmBtn) deleteConfirmBtn.disabled = false;
        });
    },
  };

  // Charts are rendered last and are fully isolated — from the rest of the
  // page (a Chart.js/data problem here can't take down project editing or
  // member management above) AND from each other (each chart gets its own
  // try/catch; previously both charts shared one try/catch, so an exception
  // building the FIRST chart — e.g. from stale/unexpected data — silently
  // aborted the second one too, leaving both canvases blank with no visible
  // error). A genuinely empty dataset (a brand-new project with no tasks
  // yet) is shown as a plain "No tasks yet." message instead of an empty
  // chart, so a blank card never has to be puzzled out as broken vs. simply empty.
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js failed to load — skipping project charts.');
    return;
  }

  function renderChart(canvasId, emptyId, hasData, buildConfig) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const emptyEl = document.getElementById(emptyId);
    if (!hasData) {
      canvas.classList.add('d-none');
      if (emptyEl) emptyEl.classList.remove('d-none');
      return;
    }
    try {
      // Guards against "Canvas is already in use" if this ever runs twice
      // against the same canvas (e.g. a future re-render without a full
      // page reload) — Chart.getChart() returns the existing instance, if any.
      const existing = typeof Chart.getChart === 'function' ? Chart.getChart(canvas) : null;
      if (existing) existing.destroy();
      new Chart(canvas, buildConfig());
    } catch (err) {
      console.warn(`Failed to render #${canvasId}:`, err);
      canvas.classList.add('d-none');
      if (emptyEl) emptyEl.classList.remove('d-none');
    }
  }

  renderChart('statusChart', 'statusChartEmpty', (P.statusData || []).length > 0, () => ({
    type: 'doughnut',
    data: {
      labels: P.statusData.map((d) => d.status),
      datasets: [{ data: P.statusData.map((d) => d.n), backgroundColor: ['#6c757d','#4361ee','#0dcaf0','#4361ee','#212529','#ffc107','#2a9d8f','#adb5bd'] }],
    },
    options: { plugins: { legend: { position: 'bottom' } } },
  }));

  renderChart('assigneeChart', 'assigneeChartEmpty', (P.assigneeData || []).length > 0, () => ({
    type: 'bar',
    data: { labels: P.assigneeData.map((d) => d.name), datasets: [{ label: 'Tasks', data: P.assigneeData.map((d) => d.n), backgroundColor: '#4361ee' }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  }));
})();

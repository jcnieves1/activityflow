(function () {
  const P = window.AF_PROJECT;
  if (!P) return;

  const statusCtx = document.getElementById('statusChart');
  if (statusCtx) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: P.statusData.map((d) => d.status),
        datasets: [{ data: P.statusData.map((d) => d.n), backgroundColor: ['#6c757d','#4361ee','#0dcaf0','#4361ee','#212529','#ffc107','#2a9d8f','#adb5bd'] }],
      },
      options: { plugins: { legend: { position: 'bottom' } } },
    });
  }

  const assigneeCtx = document.getElementById('assigneeChart');
  if (assigneeCtx) {
    new Chart(assigneeCtx, {
      type: 'bar',
      data: { labels: P.assigneeData.map((d) => d.name), datasets: [{ label: 'Tasks', data: P.assigneeData.map((d) => d.n), backgroundColor: '#4361ee' }] },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
  }

  const memberForm = document.getElementById('memberForm');
  memberForm && memberForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(memberForm).entries());
    afFetch(window.AF_BASE_URL + 'api/projects.php', { method: 'POST', body: Object.assign({ action: 'add_member' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  window.afProjectDetail = {
    removeMember(personId) {
      if (!afConfirm('Remove this member from the project?')) return;
      afFetch(window.AF_BASE_URL + 'api/projects.php', { method: 'POST', body: { action: 'remove_member', project_id: P.id, person_id: personId } })
        .then(() => location.reload())
        .catch((err) => afToast(err.message, 'danger'));
    },
  };
})();

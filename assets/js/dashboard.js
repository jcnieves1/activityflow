(function () {
  const D = window.AF_DASHBOARD;
  if (!D || typeof Chart === 'undefined') return;

  const pu = document.getElementById('plannedUnplannedChart');
  if (pu) {
    new Chart(pu, {
      type: 'doughnut',
      data: { labels: ['Planned', 'Unplanned'], datasets: [{ data: [D.plannedMinutes, D.unplannedMinutes], backgroundColor: ['#4361ee', '#f4a261'] }] },
      options: { plugins: { legend: { position: 'bottom' } } },
    });
  }

  const weeklyEl = document.getElementById('weeklyChart');
  if (weeklyEl) {
    const labels = D.weekly.map((w) => w.activity_type);
    new Chart(weeklyEl, {
      type: 'bar',
      data: {
        labels: ['This week'],
        datasets: D.weekly.map((w, i) => ({
          label: w.activity_type + ' (est.)', data: [Math.round(w.est / 60 * 10) / 10],
          backgroundColor: w.activity_type === 'planned' ? '#4361ee' : '#f4a261',
        })).concat(D.weekly.map((w) => ({
          label: w.activity_type + ' (actual)', data: [Math.round(w.actual / 60 * 10) / 10],
          backgroundColor: w.activity_type === 'planned' ? '#8d99ea' : '#f9c495',
        }))),
      },
      options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { title: { display: true, text: 'Hours' } } } },
    });
  }

  const srcEl = document.getElementById('sourceChart');
  if (srcEl) {
    new Chart(srcEl, {
      type: 'pie',
      data: { labels: D.bySource.map((s) => s.label), datasets: [{ data: D.bySource.map((s) => s.n), backgroundColor: ['#4361ee','#f4a261','#e63946','#2a9d8f','#adb5bd','#ffc107','#6c757d','#0dcaf0','#212529','#8d99ea'] }] },
      options: { plugins: { legend: { position: 'bottom' } } },
    });
  }

  if (D.employeeWorkload) {
    const employees = [...new Set(D.employeeWorkload.map((r) => r.full_name))];
    const plannedData = employees.map((name) => {
      const row = D.employeeWorkload.find((r) => r.full_name === name && r.activity_type === 'planned');
      return row ? Math.round(row.minutes / 60 * 10) / 10 : 0;
    });
    const unplannedData = employees.map((name) => {
      const row = D.employeeWorkload.find((r) => r.full_name === name && r.activity_type === 'unplanned');
      return row ? Math.round(row.minutes / 60 * 10) / 10 : 0;
    });
    new Chart(document.getElementById('employeeWorkloadChart'), {
      type: 'bar',
      data: { labels: employees, datasets: [
        { label: 'Planned (h)', data: plannedData, backgroundColor: '#4361ee' },
        { label: 'Unplanned (h)', data: unplannedData, backgroundColor: '#f4a261' },
      ] },
      options: { scales: { x: { stacked: true }, y: { stacked: true } } },
    });

    new Chart(document.getElementById('requesterUnplannedChart'), {
      type: 'bar',
      data: { labels: D.requesterUnplanned.map((r) => r.full_name), datasets: [{ label: 'Unplanned requests', data: D.requesterUnplanned.map((r) => r.n), backgroundColor: '#e63946' }] },
      options: { plugins: { legend: { display: false } }, indexAxis: 'y' },
    });

    new Chart(document.getElementById('departmentUnplannedChart'), {
      type: 'polarArea',
      data: { labels: D.departmentUnplanned.map((r) => r.name), datasets: [{ data: D.departmentUnplanned.map((r) => r.n) }] },
    });
  }
})();

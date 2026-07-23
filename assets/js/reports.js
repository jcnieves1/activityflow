(function () {
  let currentReport = null;
  const form = document.getElementById('reportFilters');
  const table = document.getElementById('reportTable');
  const empty = document.getElementById('reportEmpty');
  const titleEl = document.getElementById('reportTitle');
  const metaEl = document.getElementById('reportMeta');

  function currentParams(extra) {
    const data = Object.fromEntries(new FormData(form).entries());
    return new URLSearchParams(Object.assign(data, { report: currentReport }, extra || {}));
  }

  function runReport() {
    if (!currentReport) return;
    table.querySelector('thead').innerHTML = '';
    table.querySelector('tbody').innerHTML = '';
    empty.classList.add('d-none');
    fetch(window.AF_BASE_URL + 'api/reports.php?' + currentParams({ action: 'run' }).toString())
      .then((r) => r.json())
      .then((res) => {
        titleEl.textContent = res.report;
        if (!res.rows.length) {
          empty.classList.remove('d-none');
          metaEl.textContent = '0 records';
          return;
        }
        metaEl.textContent = res.sample_size + ' record(s)' +
          (form.date_from.value || form.date_to.value ? ` · ${form.date_from.value || 'earliest'} to ${form.date_to.value || 'latest'}` : ' · all dates');
        table.querySelector('thead').innerHTML = '<tr>' + res.columns.map((c) => `<th>${afEscapeHtml(c)}</th>`).join('') + '</tr>';
        table.querySelector('tbody').innerHTML = res.rows.map((row) =>
          '<tr>' + res.columns.map((c) => `<td>${afEscapeHtml(row[c])}</td>`).join('') + '</tr>'
        ).join('');
      })
      .catch(() => { empty.classList.remove('d-none'); empty.textContent = 'Unable to load this report.'; });
  }

  document.querySelectorAll('#reportList [data-report]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#reportList [data-report]').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentReport = btn.dataset.report;
      runReport();
    });
  });

  form.addEventListener('submit', (e) => { e.preventDefault(); runReport(); });

  document.getElementById('exportCsv').addEventListener('click', () => {
    if (!currentReport) { afToast('Choose a report first.', 'danger'); return; }
    window.location = window.AF_BASE_URL + 'api/reports.php?' + currentParams({ action: 'export_csv' }).toString();
  });

  document.getElementById('printReport').addEventListener('click', () => {
    if (!currentReport) { afToast('Choose a report first.', 'danger'); return; }
    window.print();
  });
})();

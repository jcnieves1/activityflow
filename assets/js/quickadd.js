// Global quick-add unplanned task modal (rapid entry — a few clicks from anywhere).
window.afQuickAdd = (function () {
  const modalEl = document.getElementById('quickAddModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('quickAddForm');
  const requestedAtField = document.getElementById('qa_requested_at');
  const interruptSelect = document.getElementById('qa_interrupted_activity_id');

  function toLocalDatetimeValue(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function loadInProgress() {
    if (!interruptSelect || !window.AF_PERSON_ID) return;
    afFetch(window.AF_BASE_URL + 'api/activities.php?action=list&assignee_id=' + window.AF_PERSON_ID + '&status=in_progress')
      .then((res) => {
        interruptSelect.innerHTML = '<option value="">None — did not interrupt anything</option>' +
          (res.activities || []).map((a) => `<option value="${a.id}">${afEscapeHtml(a.title)}</option>`).join('');
      })
      .catch(() => {});
  }

  modalEl && modalEl.addEventListener('show.bs.modal', function () {
    if (requestedAtField) requestedAtField.value = toLocalDatetimeValue(new Date());
    loadInProgress();
  });

  form && form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_adhoc = form.querySelector('[name=is_adhoc]').checked ? 1 : 0;
    afFetch(window.AF_BASE_URL + 'api/activities.php', { method: 'POST', body: Object.assign({ action: 'quick_add' }, data) })
      .then(() => {
        afToast('Unplanned task logged.');
        modal && modal.hide();
        form.reset();
        document.dispatchEvent(new CustomEvent('af:activity-created'));
        if (window.afOnActivityCreated) { window.afOnActivityCreated(); } else { setTimeout(() => location.reload(), 400); }
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  function newPerson(targetSelectId) {
    const name = window.prompt('Full name of the new person:');
    if (!name) return;
    const email = window.prompt('Email (optional):') || '';
    afFetch(window.AF_BASE_URL + 'api/people.php', { method: 'POST', body: { action: 'create', full_name: name, email } })
      .then((res) => {
        const select = document.getElementById(targetSelectId);
        const opt = document.createElement('option');
        opt.value = res.person.id;
        opt.textContent = res.person.full_name;
        opt.selected = true;
        select.appendChild(opt);
        afToast('Person added to the directory.');
      })
      .catch((err) => afToast(err.message, 'danger'));
  }

  return { newPerson };
})();

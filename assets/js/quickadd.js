// Global quick-add unplanned task modal (rapid entry — a few clicks from anywhere).
window.afQuickAdd = (function () {
  const modalEl = document.getElementById('quickAddModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('quickAddForm');
  const requestedAtField = document.getElementById('qa_requested_at');
  const interruptSelect = document.getElementById('qa_interrupted_activity_id');
  const projectSelect = document.getElementById('qa_project_id');

  function toLocalDatetimeValue(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  // Narrows the Interrupted task list to the selected Project's in-progress
  // tasks (still assigned to the current user), so picking a project lets you
  // pinpoint which task in it got interrupted instead of hunting through
  // every in-progress task you have across all projects. Choosing "No
  // project" again drops the project filter and goes back to showing all of
  // them, matching the original behavior.
  function loadInProgress() {
    if (!interruptSelect || !window.AF_PERSON_ID) return;
    const projectId = projectSelect ? projectSelect.value : '';
    let url = window.AF_BASE_URL + 'api/activities.php?action=list&assignee_id=' + window.AF_PERSON_ID + '&status=in_progress';
    if (projectId) url += '&project_id=' + encodeURIComponent(projectId);
    afFetch(url)
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
  projectSelect && projectSelect.addEventListener('change', loadInProgress);

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

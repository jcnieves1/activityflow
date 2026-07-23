// People directory page logic (also reused conceptually by the quick-add task form).
window.afPeople = (function () {
  const modalEl = document.getElementById('personModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('personForm');
  const warningBox = document.getElementById('personDuplicateWarning');
  let duplicateConfirmed = false;

  function reset() {
    form.reset();
    document.getElementById('pf_id').value = '';
    document.getElementById('personModalTitle').textContent = 'Add person';
    warningBox.classList.add('d-none');
    duplicateConfirmed = false;
  }

  function openCreate() { reset(); modal && modal.show(); }

  function openEdit(person) {
    reset();
    document.getElementById('personModalTitle').textContent = 'Edit person';
    document.getElementById('pf_id').value = person.id;
    document.getElementById('pf_full_name').value = person.full_name || '';
    document.getElementById('pf_job_title').value = person.job_title || '';
    document.getElementById('pf_department_id').value = person.department_id || '';
    document.getElementById('pf_organization').value = person.organization || '';
    document.getElementById('pf_org_role').value = person.org_role || '';
    document.getElementById('pf_email').value = person.email || '';
    document.getElementById('pf_phone').value = person.phone || '';
    document.getElementById('pf_manager_id').value = person.manager_id || '';
    document.getElementById('pf_notes').value = person.notes || '';
    duplicateConfirmed = true; // editing an existing record never needs a duplicate check
    modal && modal.show();
  }

  function toggleActive(id, makeActive) {
    if (!afConfirm(makeActive ? 'Reactivate this person?' : 'Deactivate this person? They will remain visible on historical activities.')) return;
    afFetch(window.AF_BASE_URL + 'api/people.php', { method: 'POST', body: { action: 'set_active', id, active: makeActive } })
      .then(() => location.reload())
      .catch((e) => afToast(e.message, 'danger'));
  }

  form && form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    const isEdit = !!data.id;

    const submit = () => {
      afFetch(window.AF_BASE_URL + 'api/people.php', { method: 'POST', body: Object.assign({ action: isEdit ? 'update' : 'create' }, data) })
        .then(() => { afToast('Person saved.'); location.reload(); })
        .catch((err) => afToast(err.message, 'danger'));
    };

    if (isEdit || duplicateConfirmed) {
      submit();
      return;
    }

    afFetch(window.AF_BASE_URL + 'api/people.php', { method: 'POST', body: { action: 'check_duplicate', full_name: data.full_name, email: data.email } })
      .then((res) => {
        if (res.matches && res.matches.length) {
          warningBox.innerHTML = 'A similar person may already exist: <strong>' +
            res.matches.map((m) => afEscapeHtml(m.full_name) + (m.email ? ' (' + afEscapeHtml(m.email) + ')' : '')).join(', ') +
            '</strong>. Click Save again to add this as a new person anyway.';
          warningBox.classList.remove('d-none');
          duplicateConfirmed = true;
        } else {
          submit();
        }
      })
      .catch(() => submit());
  });

  return { openCreate, openEdit, toggleActive };
})();

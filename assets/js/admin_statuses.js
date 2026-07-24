(function () {
  const i18n = window.AF_I18N_STATUSES || {};
  const modal = new bootstrap.Modal(document.getElementById('statusModal'));
  const deleteModal = new bootstrap.Modal(document.getElementById('statusDeleteModal'));
  const form = document.getElementById('statusForm');

  let deleting = null; // { id, label, count }

  function openCreate() {
    form.reset();
    document.getElementById('status_id').value = '';
    document.getElementById('statusModalTitle').textContent = i18n.newTitle || 'New status';
  }

  function openEdit(s) {
    document.getElementById('status_id').value = s.id;
    document.getElementById('status_label_input').value = s.label;
    document.getElementById('statusModalTitle').textContent = i18n.editTitle || 'Edit status';
    modal.show();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'status_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  // Delete is a two-step flow: a status with zero tasks on it can just be
  // removed, but one that's currently in use needs a replacement chosen
  // first — count_activities_with_status()/delete_task_status() on the
  // server are the source of truth for the count, this just presents it.
  function openDelete(s) {
    deleting = s;
    const introEl = document.getElementById('statusDeleteIntro');
    const reassignBlock = document.getElementById('statusDeleteReassignBlock');
    const warningEl = document.getElementById('statusDeleteWarning');
    const replacementSelect = document.getElementById('statusDeleteReplacement');
    const confirmLabel = document.getElementById('statusDeleteConfirmBtnLabel');

    if (s.count > 0) {
      introEl.textContent = '';
      reassignBlock.classList.remove('d-none');
      warningEl.textContent = (i18n.inUseWarning || '{count} task(s) currently use "{label}". Choose a replacement.')
        .replace('{count}', s.count).replace(/\{label\}/g, s.label);
      const options = (window.AF_TASK_STATUSES || []).filter((st) => String(st.id) !== String(s.id));
      replacementSelect.innerHTML = options.map((st) => `<option value="${afEscapeHtml(st.slug)}">${afEscapeHtml(st.label)}</option>`).join('');
      confirmLabel.textContent = i18n.reassignAndDelete || 'Reassign & delete';
    } else {
      reassignBlock.classList.add('d-none');
      introEl.textContent = (i18n.deleteSimple || 'Delete the status "{label}"? This cannot be undone.').replace(/\{label\}/g, s.label);
      confirmLabel.textContent = i18n.deleteLabel || 'Delete';
    }
    deleteModal.show();
  }

  document.getElementById('statusDeleteConfirmBtn').addEventListener('click', function () {
    if (!deleting) return;
    const body = { action: 'status_delete', id: deleting.id };
    if (deleting.count > 0) {
      const replacementSelect = document.getElementById('statusDeleteReplacement');
      if (!replacementSelect.value) { afToast('Choose a replacement status.', 'danger'); return; }
      body.replacement_slug = replacementSelect.value;
    }
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body })
      .then((res) => {
        // needs_replacement can still come back true if two admins raced each
        // other (another task got moved onto this status between opening the
        // dialog and confirming) — surface it rather than silently no-op-ing.
        if (res.needs_replacement) {
          afToast('More tasks started using this status just now — please try again.', 'danger');
          return;
        }
        afToast('Status deleted.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  window.afStatuses = { openCreate, openEdit, openDelete };
})();

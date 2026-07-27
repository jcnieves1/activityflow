(function () {
  const i18n = window.AF_I18N_RELEASE_PHASE_TEMPLATES || {};
  const modal = new bootstrap.Modal(document.getElementById('templateModal'));
  const deleteModal = new bootstrap.Modal(document.getElementById('templateDeleteModal'));
  const form = document.getElementById('templateForm');

  let deleting = null; // { id, name }

  function openCreate() {
    form.reset();
    document.getElementById('template_id').value = '';
    document.getElementById('templateModalTitle').textContent = i18n.newTitle || 'New default phase';
  }

  function openEdit(t) {
    document.getElementById('template_id').value = t.id;
    document.getElementById('template_name_input').value = t.name;
    document.getElementById('templateModalTitle').textContent = i18n.editTitle || 'Edit default phase';
    modal.show();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'release_phase_template_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  function openDelete(t) {
    deleting = t;
    document.getElementById('templateDeleteIntro').textContent =
      (i18n.deleteConfirm || 'Delete the default phase "{name}"? This cannot be undone.').replace(/\{name\}/g, t.name);
    deleteModal.show();
  }

  document.getElementById('templateDeleteConfirmBtn').addEventListener('click', function () {
    if (!deleting) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'release_phase_template_delete', id: deleting.id } })
      .then(() => {
        afToast('Default phase deleted.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  function move(id, direction) {
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'release_phase_template_move', id, direction } })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  }

  function moveUp(id) { move(id, 'up'); }
  function moveDown(id) { move(id, 'down'); }

  window.afReleasePhaseTemplates = { openCreate, openEdit, openDelete, moveUp, moveDown };
})();

// Task Templates list page: create/edit/delete the shared template library.
// Managing individual template tasks happens on task_template_detail.php.
(function () {
  const modalEl = document.getElementById('ttModal');
  if (!modalEl) { window.afTaskTemplates = {}; return; }

  const i18n = window.AF_I18N_TASK_TEMPLATES || {};
  const modal = new bootstrap.Modal(modalEl);
  const deleteModal = new bootstrap.Modal(document.getElementById('ttDeleteModal'));
  const form = document.getElementById('ttForm');

  let deleting = null; // { id, name, item_count }

  function openCreate() {
    form.reset();
    document.getElementById('tt_id').value = '';
    document.getElementById('ttModalTitle').textContent = i18n.newTitle || 'New template';
    modal.show();
  }

  function openEdit(tpl) {
    document.getElementById('tt_id').value = tpl.id;
    document.getElementById('tt_name_input').value = tpl.name;
    document.getElementById('tt_description_input').value = tpl.description || '';
    document.getElementById('ttModalTitle').textContent = i18n.editTitle || 'Edit template';
    modal.show();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    const action = data.id ? 'update' : 'create';
    afFetch(window.AF_BASE_URL + 'api/task_templates.php', { method: 'POST', body: Object.assign({ action: action }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  function openDelete(tpl) {
    deleting = tpl;
    const introEl = document.getElementById('ttDeleteIntro');
    introEl.textContent = (i18n.deleteConfirm || 'Delete the template "{name}" and its {count} task(s)? This cannot be undone.')
      .replace(/\{name\}/g, tpl.name).replace('{count}', tpl.item_count);
    deleteModal.show();
  }

  document.getElementById('ttDeleteConfirmBtn').addEventListener('click', function () {
    if (!deleting) return;
    afFetch(window.AF_BASE_URL + 'api/task_templates.php', { method: 'POST', body: { action: 'delete', id: deleting.id } })
      .then(() => {
        afToast('Template deleted.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  window.afTaskTemplates = { openCreate, openEdit, openDelete };
})();

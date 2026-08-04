// Task Template detail page: edit the template itself, and add/edit/delete/
// reorder its individual template tasks.
(function () {
  const itemModalEl = document.getElementById('ttdItemModal');
  if (!itemModalEl) { window.afTaskTemplateDetail = {}; return; }

  const i18n = window.AF_I18N_TASK_TEMPLATE_DETAIL || {};
  const itemModal = new bootstrap.Modal(itemModalEl);
  const itemDeleteModal = new bootstrap.Modal(document.getElementById('ttdItemDeleteModal'));
  const editForm = document.getElementById('ttdEditForm');
  const itemForm = document.getElementById('ttdItemForm');

  let deletingItem = null; // { id, title }

  editForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(editForm).entries());
    afFetch(window.AF_BASE_URL + 'api/task_templates.php', { method: 'POST', body: Object.assign({ action: 'update' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  function resetItemForm() {
    itemForm.reset();
    document.getElementById('ttdItem_id').value = '';
  }

  function openCreateItem() {
    resetItemForm();
    document.getElementById('ttdItemModalTitle').textContent = i18n.addTitle || 'Add task';
  }

  function openEditItem(item) {
    resetItemForm();
    document.getElementById('ttdItem_id').value = item.id;
    document.getElementById('ttdItem_title').value = item.title;
    document.getElementById('ttdItem_description').value = item.description || '';
    document.getElementById('ttdItem_priority').value = item.priority || 'normal';
    document.getElementById('ttdItem_estimated_hours').value = item.estimated_minutes ? (item.estimated_minutes / 60) : '';
    document.getElementById('ttdItem_category_id').value = item.category_id || '';
    document.getElementById('ttdItem_is_milestone').checked = !!item.is_milestone;
    document.getElementById('ttdItem_is_issue').checked = !!item.is_issue;
    document.getElementById('ttdItemModalTitle').textContent = i18n.editTitle || 'Edit task';
    itemModal.show();
  }

  itemForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(itemForm).entries());
    data.is_milestone = itemForm.querySelector('[name=is_milestone]').checked ? 1 : 0;
    data.is_issue = itemForm.querySelector('[name=is_issue]').checked ? 1 : 0;
    data.estimated_minutes = data.estimated_hours ? Math.round(parseFloat(data.estimated_hours) * 60) : '';
    delete data.estimated_hours;
    afFetch(window.AF_BASE_URL + 'api/task_templates.php', { method: 'POST', body: Object.assign({ action: 'item_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  function openDeleteItem(item) {
    deletingItem = item;
    document.getElementById('ttdItemDeleteIntro').textContent = (i18n.deleteTaskConfirm || 'Delete the task "{title}" from this template? This cannot be undone.').replace(/\{title\}/g, item.title);
    itemDeleteModal.show();
  }

  document.getElementById('ttdItemDeleteConfirmBtn').addEventListener('click', function () {
    if (!deletingItem) return;
    afFetch(window.AF_BASE_URL + 'api/task_templates.php', { method: 'POST', body: { action: 'item_delete', id: deletingItem.id } })
      .then(() => {
        afToast('Task removed from template.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  function moveItem(id, direction) {
    afFetch(window.AF_BASE_URL + 'api/task_templates.php', { method: 'POST', body: { action: 'item_move', id: id, direction: direction } })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  }

  window.afTaskTemplateDetail = { openCreateItem, openEditItem, openDeleteItem, moveItem };
})();

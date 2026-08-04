<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

// Managing the template library is restricted to admins/PMs — applying an
// existing template to a project (done from that project's own detail page)
// is a separate, broader permission; see can_add_task_to_project() and
// includes/models/task_templates.php's top-of-file note.
if (!can_manage_task_templates()) {
    deny(t('tt.no_access'));
}

$templates = list_task_templates();

$pageTitle = t('tt.title');
$activeNav = 'task_templates';
$breadcrumbs = [['label' => t('tt.title')]];
$pageScripts = [base_url('assets/js/task_templates.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('tt.title')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('tt.subtitle')) ?></p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ttModal" onclick="afTaskTemplates.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('tt.new_template')) ?></button>
</div>

<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th><?= e(t('tt.col_name')) ?></th>
      <th><?= e(t('tt.col_description')) ?></th>
      <th><?= e(t('tt.col_tasks')) ?></th>
      <th><?= e(t('tt.col_created_by')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($templates as $tpl): ?>
      <tr>
        <td class="fw-semibold"><a href="<?= e(base_url('task_template_detail.php?id=' . (int)$tpl['id'])) ?>"><?= e($tpl['name']) ?></a></td>
        <td class="text-muted small"><?= e(mb_strimwidth(strip_tags((string)$tpl['description']), 0, 120, '…')) ?></td>
        <td><?= (int)$tpl['item_count'] ?></td>
        <td class="text-muted small"><?= e($tpl['created_by_name'] ?? '—') ?></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('task_template_detail.php?id=' . (int)$tpl['id'])) ?>"><i class="bi bi-list-check"></i> <?= e(t('tt.manage')) ?></a>
          <button class="btn btn-sm btn-outline-secondary" onclick='afTaskTemplates.openEdit(<?= json_encode(['id' => (int)$tpl['id'], 'name' => $tpl['name'], 'description' => $tpl['description']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
          <button class="btn btn-sm btn-outline-danger" onclick='afTaskTemplates.openDelete(<?= json_encode(['id' => (int)$tpl['id'], 'name' => $tpl['name'], 'item_count' => (int)$tpl['item_count']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$templates): ?>
      <tr><td colspan="5"><div class="af-empty"><i class="bi bi-clipboard-check"></i><?= e(t('tt.empty')) ?></div></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="ttModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="ttForm">
    <div class="modal-header"><h5 class="modal-title" id="ttModalTitle"><?= e(t('tt.new_template')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="tt_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('tt.field_name')) ?></label>
        <input class="form-control" name="name" id="tt_name_input" required maxlength="160">
      </div>
      <div class="mb-2">
        <label class="form-label"><?= e(t('tt.field_description')) ?></label>
        <textarea class="form-control" name="description" id="tt_description_input" rows="3" placeholder="<?= e(t('tt.field_description_placeholder')) ?>"></textarea>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="ttDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('tt.delete_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p id="ttDeleteIntro"></p></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="ttDeleteConfirmBtn"><i class="bi bi-trash3"></i> <?= e(t('common.delete')) ?></button>
  </div>
</div></div></div>

<script>
window.AF_I18N_TASK_TEMPLATES = {
  newTitle: <?= json_encode(t('tt.new_template')) ?>,
  editTitle: <?= json_encode(t('tt.edit_template')) ?>,
  deleteConfirm: <?= json_encode(t('tt.delete_confirm')) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

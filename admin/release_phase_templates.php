<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$templates = list_release_phase_templates();

$pageTitle = t('admin.release_phase_templates_title');
$activeNav = 'admin_release_phase_templates';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.release_phase_templates_title')]];
$pageScripts = [base_url('assets/js/admin_release_phase_templates.js')];
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('admin.release_phase_templates_title')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('admin.release_phase_templates_subtitle')) ?></p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="afReleasePhaseTemplates.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_default_phase')) ?></button>
</div>
<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th style="width:60px;"><?= e(t('admin.col_order')) ?></th>
      <th><?= e(t('admin.col_phase_name')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($templates as $i => $tpl): ?>
      <tr>
        <td>
          <div class="btn-group-vertical btn-group-sm">
            <button class="btn btn-outline-secondary py-0 px-1" title="<?= e(t('admin.move_up')) ?>" <?= $i === 0 ? 'disabled' : '' ?> onclick="afReleasePhaseTemplates.moveUp(<?= (int)$tpl['id'] ?>)"><i class="bi bi-caret-up-fill"></i></button>
            <button class="btn btn-outline-secondary py-0 px-1" title="<?= e(t('admin.move_down')) ?>" <?= $i === count($templates) - 1 ? 'disabled' : '' ?> onclick="afReleasePhaseTemplates.moveDown(<?= (int)$tpl['id'] ?>)"><i class="bi bi-caret-down-fill"></i></button>
          </div>
        </td>
        <td class="fw-semibold"><?= $i + 1 ?>. <?= e($tpl['name']) ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='afReleasePhaseTemplates.openEdit(<?= json_encode(['id' => (int)$tpl['id'], 'name' => $tpl['name']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
          <button class="btn btn-sm btn-outline-danger" onclick='afReleasePhaseTemplates.openDelete(<?= json_encode(['id' => (int)$tpl['id'], 'name' => $tpl['name']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$templates): ?>
      <tr><td colspan="3" class="text-center text-muted py-4"><?= e(t('admin.no_default_phases')) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="templateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="templateForm">
    <div class="modal-header"><h5 class="modal-title" id="templateModalTitle"><?= e(t('admin.new_default_phase')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="template_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('admin.phase_name_label')) ?></label>
        <input class="form-control" name="name" id="template_name_input" required maxlength="80">
        <div class="form-text"><?= e(t('admin.default_phase_hint')) ?></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="templateDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('admin.delete_default_phase_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p id="templateDeleteIntro"></p></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="templateDeleteConfirmBtn"><i class="bi bi-trash3"></i> <?= e(t('common.delete')) ?></button>
  </div>
</div></div></div>

<script>
window.AF_I18N_RELEASE_PHASE_TEMPLATES = {
  newTitle: <?= json_encode(t('admin.new_default_phase')) ?>,
  editTitle: <?= json_encode(t('admin.edit_default_phase')) ?>,
  deleteConfirm: <?= json_encode(t('admin.delete_default_phase_confirm')) ?>
};
</script>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>

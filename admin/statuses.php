<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$statuses = list_task_statuses();
$statusesWithCounts = array_map(function ($s) {
    $s['task_count'] = count_activities_with_status($s['slug']);
    return $s;
}, $statuses);

$pageTitle = t('admin.statuses_title');
$activeNav = 'admin_statuses';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.statuses_title')]];
$pageScripts = [base_url('assets/js/admin_statuses.js')];
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('admin.statuses_title')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('admin.statuses_subtitle')) ?></p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal" onclick="afStatuses.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_status')) ?></button>
</div>
<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th><?= e(t('admin.col_status_text')) ?></th>
      <th><?= e(t('admin.col_slug')) ?></th>
      <th><?= e(t('admin.col_tasks_using')) ?></th>
      <th></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($statusesWithCounts as $s): ?>
      <tr>
        <td class="fw-semibold"><?= e($s['label']) ?></td>
        <td><code class="small text-muted"><?= e($s['slug']) ?></code></td>
        <td><?= (int)$s['task_count'] ?></td>
        <td><?php if ($s['is_system']): ?><span class="badge bg-secondary" title="<?= e(t('admin.system_status_hint')) ?>"><?= e(t('admin.system_status')) ?></span><?php endif; ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='afStatuses.openEdit(<?= json_encode(['id' => (int)$s['id'], 'label' => $s['label']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
          <?php if (!$s['is_system']): ?>
            <button class="btn btn-sm btn-outline-danger" onclick='afStatuses.openDelete(<?= json_encode(['id' => (int)$s['id'], 'label' => $s['label'], 'count' => (int)$s['task_count']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="statusForm">
    <div class="modal-header"><h5 class="modal-title" id="statusModalTitle"><?= e(t('admin.new_status')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="status_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('admin.status_text_label')) ?></label>
        <input class="form-control" name="label" id="status_label_input" required maxlength="80">
        <div class="form-text"><?= e(t('admin.status_text_hint')) ?></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="statusDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('admin.delete_status_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p id="statusDeleteIntro"></p>
    <div id="statusDeleteReassignBlock" class="d-none">
      <div class="alert alert-warning" id="statusDeleteWarning"></div>
      <label class="form-label"><?= e(t('admin.replacement_status')) ?></label>
      <select class="form-select" id="statusDeleteReplacement"></select>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="statusDeleteConfirmBtn"><i class="bi bi-trash3"></i> <span id="statusDeleteConfirmBtnLabel"><?= e(t('common.delete')) ?></span></button>
  </div>
</div></div></div>

<script>
window.AF_TASK_STATUSES = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'slug' => $s['slug'], 'label' => $s['label']], $statuses)) ?>;
window.AF_I18N_STATUSES = {
  deleteSimple: <?= json_encode(t('admin.delete_status_simple_confirm')) ?>,
  inUseWarning: <?= json_encode(t('admin.delete_status_in_use_warning')) ?>,
  reassignAndDelete: <?= json_encode(t('admin.reassign_and_delete')) ?>,
  newTitle: <?= json_encode(t('admin.new_status')) ?>,
  editTitle: <?= json_encode(t('admin.edit_status')) ?>,
  deleteLabel: <?= json_encode(t('common.delete')) ?>
};
</script>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>

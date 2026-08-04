<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

if (!can_manage_task_templates()) {
    deny(t('tt.no_access'));
}

$templateId = (int)($_GET['id'] ?? 0);
$template = get_task_template($templateId);
if (!$template) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$items = list_task_template_items($templateId);
$categories = db()->query('SELECT * FROM activity_categories WHERE is_active = 1 ORDER BY name')->fetchAll();

$pageTitle = $template['name'];
$activeNav = 'task_templates';
$breadcrumbs = [
    ['label' => t('tt.title'), 'url' => base_url('task_templates.php')],
    ['label' => $template['name']],
];
$pageScripts = [base_url('assets/js/task_template_detail.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <a href="<?= e(base_url('task_templates.php')) ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(t('tt.back_to_templates')) ?></a>
    <h4 class="mb-0"><?= e($template['name']) ?></h4>
    <?php if ($template['description']): ?><p class="text-muted small mt-1 mb-0"><?= nl2br(e($template['description'])) ?></p><?php endif; ?>
  </div>
  <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ttdEditModal"><i class="bi bi-pencil-square"></i> <?= e(t('tt.edit_template')) ?></button>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 mt-4">
  <h5 class="mb-0"><?= e(t('tt.tasks_title')) ?></h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ttdItemModal" onclick="afTaskTemplateDetail.openCreateItem()"><i class="bi bi-plus-lg"></i> <?= e(t('tt.add_task')) ?></button>
</div>

<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th></th>
      <th><?= e(t('tt.col_task_title')) ?></th>
      <th><?= e(t('common.priority')) ?></th>
      <th><?= e(t('tt.col_estimate')) ?></th>
      <th><?= e(t('activity.field_category')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
      <tr>
        <td class="text-nowrap">
          <button type="button" class="btn btn-sm btn-link p-0 <?= $i === 0 ? 'disabled' : '' ?>" title="<?= e(t('tt.move_up')) ?>" onclick="afTaskTemplateDetail.moveItem(<?= (int)$it['id'] ?>, 'up')"><i class="bi bi-arrow-up-circle"></i></button>
          <button type="button" class="btn btn-sm btn-link p-0 <?= $i === count($items) - 1 ? 'disabled' : '' ?>" title="<?= e(t('tt.move_down')) ?>" onclick="afTaskTemplateDetail.moveItem(<?= (int)$it['id'] ?>, 'down')"><i class="bi bi-arrow-down-circle"></i></button>
        </td>
        <td class="fw-semibold">
          <?= e($it['title']) ?>
          <?php if ($it['is_milestone']): ?> <i class="bi bi-flag-fill text-warning" title="<?= e(t('tasks.milestone')) ?>"></i><?php endif; ?>
          <?php if ($it['is_issue']): ?> <i class="bi bi-exclamation-octagon-fill text-danger" title="<?= e(t('tasks.issue_tooltip')) ?>"></i><?php endif; ?>
        </td>
        <td><span class="badge <?= priority_badge_class($it['priority']) ?>"><?= e(status_label($it['priority'])) ?></span></td>
        <td><?= e(format_minutes($it['estimated_minutes'] !== null ? (int)$it['estimated_minutes'] : null)) ?></td>
        <td><?= $it['category_name'] ? e($it['category_name']) : '<span class="text-muted">—</span>' ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='afTaskTemplateDetail.openEditItem(<?= json_encode([
              'id' => (int)$it['id'], 'title' => $it['title'], 'description' => $it['description'],
              'priority' => $it['priority'], 'estimated_minutes' => $it['estimated_minutes'],
              'category_id' => $it['category_id'], 'is_milestone' => (bool)$it['is_milestone'], 'is_issue' => (bool)$it['is_issue'],
          ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
          <button class="btn btn-sm btn-outline-danger" onclick='afTaskTemplateDetail.openDeleteItem(<?= json_encode(['id' => (int)$it['id'], 'title' => $it['title']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <tr><td colspan="6"><div class="af-empty"><i class="bi bi-list-check"></i><?= e(t('tt.no_tasks')) ?></div></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="ttdEditModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="ttdEditForm">
    <input type="hidden" name="id" value="<?= (int)$template['id'] ?>">
    <div class="modal-header"><h5 class="modal-title"><?= e(t('tt.edit_template')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2">
        <label class="form-label"><?= e(t('tt.field_name')) ?></label>
        <input class="form-control" name="name" value="<?= e($template['name']) ?>" required maxlength="160">
      </div>
      <div class="mb-2">
        <label class="form-label"><?= e(t('tt.field_description')) ?></label>
        <textarea class="form-control" name="description" rows="3"><?= e($template['description']) ?></textarea>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="ttdItemModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="ttdItemForm">
    <input type="hidden" name="id" id="ttdItem_id">
    <input type="hidden" name="template_id" value="<?= (int)$template['id'] ?>">
    <div class="modal-header"><h5 class="modal-title" id="ttdItemModalTitle"><?= e(t('tt.add_task')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2">
        <label class="form-label"><?= e(t('activity.field_title')) ?></label>
        <input class="form-control" name="title" id="ttdItem_title" required maxlength="220">
      </div>
      <div class="mb-2">
        <label class="form-label"><?= e(t('activity.field_description')) ?></label>
        <textarea class="form-control" name="description" id="ttdItem_description" rows="2"></textarea>
      </div>
      <div class="row">
        <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('common.priority')) ?></label>
          <select class="form-select" name="priority" id="ttdItem_priority">
            <option value="low"><?= e(t('activity.priority_low')) ?></option>
            <option value="normal" selected><?= e(t('activity.priority_normal')) ?></option>
            <option value="high"><?= e(t('activity.priority_high')) ?></option>
            <option value="urgent"><?= e(t('activity.priority_urgent')) ?></option>
          </select>
        </div>
        <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('activity.field_estimated_hours')) ?></label>
          <input type="number" min="0" step="0.25" class="form-control" name="estimated_hours" id="ttdItem_estimated_hours" placeholder="e.g. 1.5">
        </div>
        <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('activity.field_category')) ?></label>
          <select class="form-select" name="category_id" id="ttdItem_category_id">
            <option value="">—</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input" name="is_milestone" id="ttdItem_is_milestone" value="1">
        <label class="form-check-label" for="ttdItem_is_milestone"><?= e(t('activity.field_milestone')) ?></label>
      </div>
      <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input" name="is_issue" id="ttdItem_is_issue" value="1">
        <label class="form-check-label" for="ttdItem_is_issue"><?= e(t('activity.field_issue')) ?></label>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="ttdItemDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('tt.delete_task_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p id="ttdItemDeleteIntro"></p></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="ttdItemDeleteConfirmBtn"><i class="bi bi-trash3"></i> <?= e(t('common.delete')) ?></button>
  </div>
</div></div></div>

<script>
window.AF_I18N_TASK_TEMPLATE_DETAIL = {
  addTitle: <?= json_encode(t('tt.add_task')) ?>,
  editTitle: <?= json_encode(t('tt.edit_task')) ?>,
  deleteTaskConfirm: <?= json_encode(t('tt.delete_task_confirm')) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

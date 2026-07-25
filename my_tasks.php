<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$personId = current_person_id();
$filters = [
    'assignee_id' => $personId,
    'status' => $_GET['status'] ?? '',
    'activity_type' => $_GET['activity_type'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'search' => $_GET['search'] ?? '',
    'order_by' => 'FIELD(a.status,"in_progress","blocked","ready","planned","backlog","waiting","completed","cancelled"), a.target_completion_at IS NULL, a.target_completion_at',
];
$activities = $personId ? list_activities($filters) : [];
$projects = list_projects(['is_archived' => 0]);
$interruptedTaskIds = array_flip(activity_ids_that_were_interrupted(array_column($activities, 'id')));

$pageTitle = t('nav.my_tasks');
$activeNav = 'my_tasks';
$breadcrumbs = [['label' => t('nav.my_tasks')]];
$pageScripts = [base_url('assets/js/activities.js'), base_url('assets/js/my_tasks.js')];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><?= e(t('nav.my_tasks')) ?></h4>
  <button class="btn btn-primary" onclick="afActivities.openCreate({assignee_id: <?= (int)$personId ?>})"><i class="bi bi-plus-lg"></i> <?= e(t('tasks.new_task')) ?></button>
</div>

<?php if (!$personId): ?>
  <div class="af-empty"><i class="bi bi-person-x"></i><?= e(t('tasks.no_person_linked')) ?></div>
<?php else: ?>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-3"><input type="text" class="form-control" name="search" placeholder="<?= e(t('tasks.search')) ?>" value="<?= e($_GET['search'] ?? '') ?>"></div>
  <div class="col-md-2">
    <select class="form-select" name="status"><option value=""><?= e(t('tasks.all_statuses')) ?></option>
      <?php foreach (list_task_statuses() as $st): ?><option value="<?= e($st['slug']) ?>" <?= ($_GET['status'] ?? '') === $st['slug'] ? 'selected' : '' ?>><?= e($st['label']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="activity_type"><option value=""><?= e(t('tasks.planned_and_unplanned')) ?></option>
      <option value="planned" <?= ($_GET['activity_type'] ?? '') === 'planned' ? 'selected' : '' ?>><?= e(t('tasks.planned')) ?></option>
      <option value="unplanned" <?= ($_GET['activity_type'] ?? '') === 'unplanned' ? 'selected' : '' ?>><?= e(t('tasks.unplanned')) ?></option>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="priority"><option value=""><?= e(t('tasks.all_priorities')) ?></option>
      <?php foreach (ACTIVITY_PRIORITIES as $p): ?><option value="<?= $p ?>" <?= ($_GET['priority'] ?? '') === $p ? 'selected' : '' ?>><?= e(status_label($p)) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="project_id"><option value=""><?= e(t('tasks.all_projects')) ?></option>
      <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['project_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-1"><button class="btn btn-outline-secondary w-100"><?= e(t('tasks.go')) ?></button></div>
</form>

<div class="d-none align-items-center gap-2 mb-2" id="af-bulk-bar">
  <span class="text-muted small" id="af-bulk-count"></span>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="af-bulk-clone"><i class="bi bi-files"></i> <?= e(t('tasks.clone_selected')) ?></button>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="af-bulk-move"><i class="bi bi-arrow-left-right"></i> <?= e(t('tasks.move_selected')) ?></button>
</div>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th style="width:2rem"><input type="checkbox" id="af-select-all" aria-label="<?= e(t('tasks.select_all')) ?>"></th><th><?= e(t('tasks.col_task')) ?></th><th><?= e(t('tasks.col_type')) ?></th><th><?= e(t('tasks.col_project')) ?></th><th><?= e(t('tasks.col_requester')) ?></th><th><?= e(t('tasks.col_priority')) ?></th><th><?= e(t('tasks.col_status')) ?></th><th><?= e(t('tasks.col_target')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($activities as $a): ?>
        <tr>
          <td><input type="checkbox" class="af-task-select" value="<?= (int)$a['id'] ?>" aria-label="<?= e(t('tasks.select_task')) ?>"></td>
          <td class="fw-semibold"><?= e($a['title']) ?><?= $a['is_milestone'] ? ' <i class="bi bi-flag-fill text-warning" title="' . e(t('tasks.milestone')) . '"></i>' : '' ?><?= isset($interruptedTaskIds[(int)$a['id']]) ? ' <i class="bi bi-lightning-charge-fill text-orange" title="' . e(t('tasks.interrupted_tooltip')) . '"></i>' : '' ?></td>
          <td><?= activity_type_badge($a['activity_type']) ?></td>
          <td><?= $a['project_name'] ? e($a['project_name']) : '<span class="text-muted">' . e(t('tasks.no_project')) . '</span>' ?></td>
          <td><?= e($a['requester_name']) ?></td>
          <td><span class="badge <?= priority_badge_class($a['priority']) ?>"><?= e(status_label($a['priority'])) ?></span></td>
          <td><span class="badge <?= status_badge_class($a['status']) ?>"><?= e(task_status_label($a['status'])) ?></span></td>
          <td class="small"><?= e(format_datetime($a['target_completion_at'])) ?></td>
          <td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)"><?= e(t('common.open')) ?></button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$activities): ?>
        <tr><td colspan="9"><div class="af-empty"><i class="bi bi-check2-square"></i><?= e(t('tasks.empty')) ?></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<script>window.AF_OPEN_ACTIVITY = <?= json_encode((int)($_GET['activity'] ?? 0)) ?>;</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

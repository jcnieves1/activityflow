<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$filters = [
    'assignee_id' => $_GET['employee_id'] ?? '',
    'requester_id' => $_GET['requester_id'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'status' => $_GET['status'] ?? '',
    'activity_type' => $_GET['activity_type'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? '',
    'limit' => 300,
    'order_by' => 'a.created_at DESC',
];
$all = list_activities($filters);

// Enforce project-level visibility for non-admin/viewer roles.
if (!is_admin() && !user_has_role(ROLE_VIEWER)) {
    $all = array_values(array_filter($all, function ($a) {
        if (empty($a['project_id'])) return true;
        $project = get_project((int)$a['project_id']);
        return $project && can_view_project($project);
    }));
}

$people = list_people(['is_active' => 1]);
$projects = list_projects(['is_archived' => 0]);
$vacationConflicts = bulk_activity_vacation_conflicts(array_column($all, 'id'));

$pageTitle = t('nav.team');
$activeNav = 'team';
$breadcrumbs = [['label' => t('nav.team')]];
$pageScripts = [base_url('assets/js/activities.js'), base_url('assets/js/team_activities.js')];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<h4 class="mb-3"><?= e(t('nav.team')) ?></h4>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-3"><input type="text" class="form-control" name="search" placeholder="<?= e(t('tasks.search')) ?>" value="<?= e($_GET['search'] ?? '') ?>"></div>
  <div class="col-md-2"><select class="form-select" name="employee_id"><option value=""><?= e(t('tasks.all_employees')) ?></option>
    <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['employee_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><select class="form-select" name="requester_id"><option value=""><?= e(t('tasks.all_requesters')) ?></option>
    <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['requester_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><select class="form-select" name="project_id"><option value=""><?= e(t('tasks.all_projects')) ?></option>
    <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['project_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-md-1"><select class="form-select" name="activity_type"><option value=""><?= e(t('common.type')) ?></option>
    <option value="planned" <?= ($_GET['activity_type'] ?? '') === 'planned' ? 'selected' : '' ?>><?= e(t('tasks.planned')) ?></option>
    <option value="unplanned" <?= ($_GET['activity_type'] ?? '') === 'unplanned' ? 'selected' : '' ?>><?= e(t('tasks.unplanned')) ?></option>
  </select></div>
  <div class="col-md-2"><select class="form-select" name="status"><option value=""><?= e(t('common.status')) ?></option>
    <?php foreach (list_task_statuses() as $st): ?><option value="<?= e($st['slug']) ?>" <?= ($_GET['status'] ?? '') === $st['slug'] ? 'selected' : '' ?>><?= e($st['label']) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><input type="date" class="form-control" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
  <div class="col-md-2"><input type="date" class="form-control" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><?= e(t('common.filter')) ?></button></div>
</form>

<div class="d-none align-items-center gap-2 mb-2" id="af-bulk-bar">
  <span class="text-muted small" id="af-bulk-count"></span>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="af-bulk-clone"><i class="bi bi-files"></i> <?= e(t('tasks.clone_selected')) ?></button>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="af-bulk-move"><i class="bi bi-arrow-left-right"></i> <?= e(t('tasks.move_selected')) ?></button>
</div>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th style="width:2rem"><input type="checkbox" id="af-select-all" aria-label="<?= e(t('tasks.select_all')) ?>"></th><th><?= e(t('tasks.col_task')) ?></th><th><?= e(t('tasks.col_type')) ?></th><th><?= e(t('tasks.col_assignee')) ?></th><th><?= e(t('tasks.col_requester')) ?></th><th><?= e(t('tasks.col_project')) ?></th><th><?= e(t('tasks.col_status')) ?></th><th><?= e(t('tasks.col_requested')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($all as $a): ?>
        <tr>
          <td><input type="checkbox" class="af-task-select" value="<?= (int)$a['id'] ?>" aria-label="<?= e(t('tasks.select_task')) ?>"></td>
          <td class="fw-semibold"><?= e($a['title']) ?><?= isset($vacationConflicts[(int)$a['id']]) ? ' <i class="bi bi-airplane-engines-fill text-danger" title="' . e(t('tasks.vacation_conflict_tooltip')) . '"></i>' : '' ?></td>
          <td><?= activity_type_badge($a['activity_type']) ?></td>
          <td><?= e($a['assignee_name']) ?></td>
          <td><?= e($a['requester_name']) ?></td>
          <td><?= $a['project_name'] ? e($a['project_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><span class="badge <?= status_badge_class($a['status']) ?>"><?= e(task_status_label($a['status'])) ?></span></td>
          <td class="small"><?= e(format_datetime($a['requested_at'])) ?></td>
          <td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)"><?= e(t('common.open')) ?></button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$all): ?><tr><td colspan="9"><div class="af-empty"><i class="bi bi-people"></i><?= e(t('tasks.no_activities_match')) ?></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

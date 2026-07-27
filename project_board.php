<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$projectId = (int)($_GET['id'] ?? 0);
$project = get_project($projectId);
if (!$project) { http_response_code(404); require __DIR__ . '/404.php'; exit; }
if (!can_view_project($project)) deny(t('pd.no_access'));

$members = list_project_members($projectId);
$selectedMemberIds = array_values(array_unique(array_map('intval', array_filter((array)($_GET['members'] ?? []), fn($v) => $v !== ''))));
$allStatuses = list_task_statuses();
$allStatusSlugs = array_column($allStatuses, 'slug');
$selectedStatuses = array_values(array_intersect($allStatusSlugs, (array)($_GET['statuses'] ?? [])));

$activityFilters = ['project_id' => $projectId, 'limit' => 500];
if ($selectedMemberIds) {
    $activityFilters['assignee_id_in'] = $selectedMemberIds;
}
if ($selectedStatuses) {
    $activityFilters['status_in'] = $selectedStatuses;
}
$activities = list_activities($activityFilters);
$vacationConflicts = bulk_activity_vacation_conflicts(array_column($activities, 'id'));
$byStatus = [];
foreach ($allStatusSlugs as $s) { $byStatus[$s] = []; }
foreach ($activities as $a) { $byStatus[$a['status']][] = $a; }
$displayStatuses = $selectedStatuses ?: $allStatusSlugs;

$pageTitle = t('board.title', ['name' => $project['name']]);
$activeNav = 'projects';
$breadcrumbs = [['label' => t('projects.title'), 'url' => base_url('projects.php')], ['label' => $project['name'], 'url' => base_url('project_detail.php?id=' . $projectId)], ['label' => t('pd.task_board')]];
$pageScripts = [base_url('assets/js/activities.js'), base_url('assets/js/project_board.js')];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><?= e(t('board.title', ['name' => $project['name']])) ?></h4>
  <div class="d-flex align-items-center gap-2">
    <form method="get" class="d-flex align-items-center gap-2" id="boardFilterForm">
      <input type="hidden" name="id" value="<?= $projectId ?>">

      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="boardMemberFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-people"></i> <span id="boardMemberFilterLabel"><?= $selectedMemberIds ? e(t('board.selected_suffix', ['count' => count($selectedMemberIds), 'noun' => count($selectedMemberIds) === 1 ? t('board.member_singular') : t('board.member_plural')])) : e(t('board.all_team_members')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:260px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="boardFilterAll" <?= !$selectedMemberIds ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="boardFilterAll"><?= e(t('board.all_team_members')) ?></label>
          </div>
          <?php if (!$members): ?>
            <div class="text-muted small"><?= e(t('board.no_members_yet')) ?></div>
          <?php endif; ?>
          <?php foreach ($members as $m): ?>
            <div class="form-check">
              <input class="form-check-input board-member-checkbox" type="checkbox" name="members[]" value="<?= (int)$m['person_id'] ?>" id="boardMember<?= (int)$m['person_id'] ?>" <?= in_array((int)$m['person_id'], $selectedMemberIds, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="boardMember<?= (int)$m['person_id'] ?>"><?= e($m['full_name']) ?></label>
            </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-sm btn-primary w-100 mt-3"><?= e(t('common.apply')) ?></button>
        </div>
      </div>

      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="boardStatusFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-flag"></i> <span id="boardStatusFilterLabel"><?= $selectedStatuses ? e(t('board.selected_suffix', ['count' => count($selectedStatuses), 'noun' => count($selectedStatuses) === 1 ? t('board.status_singular') : t('board.status_plural')])) : e(t('board.all_statuses')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:220px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="boardStatusFilterAll" <?= !$selectedStatuses ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="boardStatusFilterAll"><?= e(t('board.all_statuses')) ?></label>
          </div>
          <?php foreach ($allStatuses as $st): $s = $st['slug']; ?>
            <div class="form-check">
              <input class="form-check-input board-status-checkbox" type="checkbox" name="statuses[]" value="<?= e($s) ?>" id="boardStatus<?= e($s) ?>" <?= in_array($s, $selectedStatuses, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="boardStatus<?= e($s) ?>"><?= e($st['label']) ?></label>
            </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-sm btn-primary w-100 mt-3"><?= e(t('common.apply')) ?></button>
        </div>
      </div>
    </form>

    <button class="btn btn-primary" onclick="afActivities.openCreate({project_id: <?= $projectId ?>})"><i class="bi bi-plus-lg"></i> <?= e(t('board.add_task')) ?></button>
  </div>
</div>

<?php if (count($displayStatuses) < count($allStatusSlugs)): ?>
  <p class="text-muted small mb-2"><?= e(t('board.showing_statuses', ['shown' => count($displayStatuses), 'total' => count($allStatusSlugs)])) ?></p>
<?php endif; ?>
<div class="af-board d-flex gap-3" style="overflow-x:auto;">
  <?php foreach ($displayStatuses as $status): ?>
    <div class="af-board-col" style="min-width:260px;flex:1;">
      <div class="fw-semibold text-muted small text-uppercase mb-2"><?= e(task_status_label($status)) ?> <span class="badge bg-light text-dark"><?= count($byStatus[$status]) ?></span></div>
      <div class="af-dropzone" data-status="<?= e($status) ?>" style="min-height:120px;">
        <?php foreach ($byStatus[$status] as $a):
          $cls = $a['status'] === 'completed' ? 'completed' : ($a['status'] === 'blocked' ? 'blocked' : ($a['activity_type'] === 'unplanned' ? 'unplanned' : ''));
          if ($a['priority'] === 'urgent') $cls = 'urgent';
        ?>
        <div class="af-activity-item <?= $cls ?>" draggable="true" data-id="<?= (int)$a['id'] ?>" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)">
          <div class="title"><?= e($a['title']) ?><?= isset($vacationConflicts[(int)$a['id']]) ? ' <i class="bi bi-airplane-engines-fill text-danger" title="' . e(t('tasks.vacation_conflict_tooltip')) . '"></i>' : '' ?></div>
          <div class="small text-muted"><?= e($a['assignee_name']) ?></div>
          <div class="mt-1"><?= activity_type_badge($a['activity_type']) ?> <span class="badge <?= priority_badge_class($a['priority']) ?>"><?= e(status_label($a['priority'])) ?></span></div>
          <div class="af-card-progress mt-2 d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:5px;">
              <div class="progress-bar" role="progressbar" style="width:<?= (int)$a['completion_pct'] ?>%;" aria-valuenow="<?= (int)$a['completion_pct'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span class="small text-muted"><?= (int)$a['completion_pct'] ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

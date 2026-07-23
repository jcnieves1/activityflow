<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$projectId = (int)($_GET['id'] ?? 0);
$project = get_project($projectId);
if (!$project) { http_response_code(404); require __DIR__ . '/404.php'; exit; }
if (!can_view_project($project)) deny('You do not have access to this project.');

$members = list_project_members($projectId);
$selectedMemberIds = array_values(array_unique(array_map('intval', array_filter((array)($_GET['members'] ?? []), fn($v) => $v !== ''))));

$activityFilters = ['project_id' => $projectId, 'limit' => 500];
if ($selectedMemberIds) {
    $activityFilters['assignee_id_in'] = $selectedMemberIds;
}
$activities = list_activities($activityFilters);
$byStatus = [];
foreach (ACTIVITY_STATUSES as $s) { $byStatus[$s] = []; }
foreach ($activities as $a) { $byStatus[$a['status']][] = $a; }

$pageTitle = $project['name'] . ' — Task Board';
$activeNav = 'projects';
$breadcrumbs = [['label' => 'Projects', 'url' => base_url('projects.php')], ['label' => $project['name'], 'url' => base_url('project_detail.php?id=' . $projectId)], ['label' => 'Task board']];
$pageScripts = [base_url('assets/js/activities.js'), base_url('assets/js/project_board.js')];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><?= e($project['name']) ?> — Task board</h4>
  <div class="d-flex align-items-center gap-2">
    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="boardMemberFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi bi-people"></i> <span id="boardMemberFilterLabel"><?= $selectedMemberIds ? count($selectedMemberIds) . ' member' . (count($selectedMemberIds) === 1 ? '' : 's') . ' selected' : 'All team members' ?></span>
      </button>
      <form method="get" class="dropdown-menu p-3" style="min-width:260px;max-height:340px;overflow-y:auto;" onclick="event.stopPropagation()">
        <input type="hidden" name="id" value="<?= $projectId ?>">
        <div class="form-check mb-2 border-bottom pb-2">
          <input class="form-check-input" type="checkbox" id="boardFilterAll" <?= !$selectedMemberIds ? 'checked' : '' ?>>
          <label class="form-check-label fw-semibold" for="boardFilterAll">All team members</label>
        </div>
        <?php if (!$members): ?>
          <div class="text-muted small">No members on this project yet.</div>
        <?php endif; ?>
        <?php foreach ($members as $m): ?>
          <div class="form-check">
            <input class="form-check-input board-member-checkbox" type="checkbox" name="members[]" value="<?= (int)$m['person_id'] ?>" id="boardMember<?= (int)$m['person_id'] ?>" <?= in_array((int)$m['person_id'], $selectedMemberIds, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="boardMember<?= (int)$m['person_id'] ?>"><?= e($m['full_name']) ?></label>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-sm btn-primary w-100 mt-3">Apply filter</button>
      </form>
    </div>
    <button class="btn btn-primary" onclick="afActivities.openCreate({project_id: <?= $projectId ?>})"><i class="bi bi-plus-lg"></i> Add task</button>
  </div>
</div>

<div class="af-board d-flex gap-3" style="overflow-x:auto;">
  <?php foreach (ACTIVITY_STATUSES as $status): ?>
    <div class="af-board-col" style="min-width:260px;flex:1;">
      <div class="fw-semibold text-muted small text-uppercase mb-2"><?= e(status_label($status)) ?> <span class="badge bg-light text-dark"><?= count($byStatus[$status]) ?></span></div>
      <div class="af-dropzone" data-status="<?= $status ?>" style="min-height:120px;">
        <?php foreach ($byStatus[$status] as $a):
          $cls = $a['status'] === 'completed' ? 'completed' : ($a['status'] === 'blocked' ? 'blocked' : ($a['activity_type'] === 'unplanned' ? 'unplanned' : ''));
          if ($a['priority'] === 'urgent') $cls = 'urgent';
        ?>
        <div class="af-activity-item <?= $cls ?>" draggable="true" data-id="<?= (int)$a['id'] ?>" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)">
          <div class="title"><?= e($a['title']) ?></div>
          <div class="small text-muted"><?= e($a['assignee_name']) ?></div>
          <div class="mt-1"><?= activity_type_badge($a['activity_type']) ?> <span class="badge <?= priority_badge_class($a['priority']) ?>"><?= e(status_label($a['priority'])) ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

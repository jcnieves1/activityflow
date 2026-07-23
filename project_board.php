<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$projectId = (int)($_GET['id'] ?? 0);
$project = get_project($projectId);
if (!$project) { http_response_code(404); require __DIR__ . '/404.php'; exit; }
if (!can_view_project($project)) deny('You do not have access to this project.');

$activities = list_activities(['project_id' => $projectId, 'limit' => 500]);
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
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?= e($project['name']) ?> — Task board</h4>
  <button class="btn btn-primary" onclick="afActivities.openCreate({project_id: <?= $projectId ?>})"><i class="bi bi-plus-lg"></i> Add task</button>
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

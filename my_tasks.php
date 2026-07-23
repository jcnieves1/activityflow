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

$pageTitle = 'My Tasks';
$activeNav = 'my_tasks';
$breadcrumbs = [['label' => 'My Tasks']];
$pageScripts = [base_url('assets/js/activities.js'), base_url('assets/js/my_tasks.js')];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0">My Tasks</h4>
  <button class="btn btn-primary" onclick="afActivities.openCreate({assignee_id: <?= (int)$personId ?>})"><i class="bi bi-plus-lg"></i> New task</button>
</div>

<?php if (!$personId): ?>
  <div class="af-empty"><i class="bi bi-person-x"></i>Your login isn't linked to a person record yet. Ask an administrator to link your account.</div>
<?php else: ?>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-3"><input type="text" class="form-control" name="search" placeholder="Search…" value="<?= e($_GET['search'] ?? '') ?>"></div>
  <div class="col-md-2">
    <select class="form-select" name="status"><option value="">All statuses</option>
      <?php foreach (ACTIVITY_STATUSES as $s): ?><option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="activity_type"><option value="">Planned &amp; unplanned</option>
      <option value="planned" <?= ($_GET['activity_type'] ?? '') === 'planned' ? 'selected' : '' ?>>Planned</option>
      <option value="unplanned" <?= ($_GET['activity_type'] ?? '') === 'unplanned' ? 'selected' : '' ?>>Unplanned</option>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="priority"><option value="">All priorities</option>
      <?php foreach (ACTIVITY_PRIORITIES as $p): ?><option value="<?= $p ?>" <?= ($_GET['priority'] ?? '') === $p ? 'selected' : '' ?>><?= e(status_label($p)) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="project_id"><option value="">All projects</option>
      <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['project_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-1"><button class="btn btn-outline-secondary w-100">Go</button></div>
</form>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Task</th><th>Type</th><th>Project</th><th>Requester</th><th>Priority</th><th>Status</th><th>Target</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($activities as $a): ?>
        <tr>
          <td class="fw-semibold"><?= e($a['title']) ?><?= $a['is_milestone'] ? ' <i class="bi bi-flag-fill text-warning" title="Milestone"></i>' : '' ?></td>
          <td><?= activity_type_badge($a['activity_type']) ?></td>
          <td><?= $a['project_name'] ? e($a['project_name']) : '<span class="text-muted">No project</span>' ?></td>
          <td><?= e($a['requester_name']) ?></td>
          <td><span class="badge <?= priority_badge_class($a['priority']) ?>"><?= e(status_label($a['priority'])) ?></span></td>
          <td><span class="badge <?= status_badge_class($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
          <td class="small"><?= e(format_datetime($a['target_completion_at'])) ?></td>
          <td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)">Open</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$activities): ?>
        <tr><td colspan="8"><div class="af-empty"><i class="bi bi-check2-square"></i>No tasks match these filters.</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<script>window.AF_OPEN_ACTIVITY = <?= json_encode((int)($_GET['activity'] ?? 0)) ?>;</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

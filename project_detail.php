<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$projectId = (int)($_GET['id'] ?? 0);
$project = get_project($projectId);
if (!$project) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
if (!can_view_project($project)) {
    deny('You do not have access to this project.');
}

$canManage = can_manage_project($project);
$method = $_GET['method'] ?? 'duration_weighted';
$progress = calculate_project_progress($projectId, $method);
$stats = project_task_stats($projectId);
$members = list_project_members($projectId);
$people = list_people(['is_active' => 1]);
$departments = department_list();
$recent = array_slice(audit_history('project', $projectId), 0, 8);
$projectStatuses = ['draft', 'not_started', 'active', 'on_hold', 'completed', 'cancelled', 'archived'];

$effort = $stats['effort'];
$plannedHours = round(((int)$effort['planned_minutes']) / 60, 1);
$actualHours = round(((int)$effort['actual_minutes']) / 60, 1);

$pageTitle = $project['name'];
$activeNav = 'projects';
$breadcrumbs = [['label' => 'Projects', 'url' => base_url('projects.php')], ['label' => $project['name']]];
$pageStyles = ['https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css'];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js',
    base_url('assets/js/project_detail.js'),
];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <div class="d-flex align-items-center gap-2">
      <span class="rounded-circle d-inline-block" style="width:14px;height:14px;background:<?= e($project['color']) ?>"></span>
      <h4 class="mb-0"><?= e($project['name']) ?></h4>
      <span class="badge <?= status_badge_class($project['status']) ?>"><?= e(status_label($project['status'])) ?></span>
      <span class="badge <?= priority_badge_class($project['priority']) ?>"><?= e(status_label($project['priority'])) ?></span>
    </div>
    <div class="text-muted small mt-1"><?= e($project['code']) ?> · Owner: <?= e($project['owner_name']) ?>
      <?= $project['start_date'] ? ' · Start: ' . e(format_date($project['start_date'])) : '' ?>
      <?= $project['target_completion_date'] ? ' · Target: ' . e(format_date($project['target_completion_date'])) : '' ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('project_board.php?id=' . $projectId)) ?>" class="btn btn-outline-primary"><i class="bi bi-kanban"></i> Task board</a>
    <?php if ($canManage): ?>
      <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProjectModal"><i class="bi bi-pencil-square"></i> Edit project</button>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memberModal"><i class="bi bi-person-plus"></i> Add member</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($project['description']): ?><div class="text-muted af-rich-text mb-3"><?= $project['description'] /* sanitized on write via sanitize_html() — safe to echo raw */ ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat"><?= (float)$progress['percent'] ?>%</div>
      <div class="text-muted small">Progress (<?= $progress['method'] === 'duration_weighted' ? 'duration-weighted' : 'simple task count' ?>)</div>
      <div class="btn-group btn-group-sm mt-2">
        <a class="btn btn-outline-secondary <?= $method === 'duration_weighted' ? 'active' : '' ?>" href="?id=<?= $projectId ?>&method=duration_weighted">Duration-weighted</a>
        <a class="btn btn-outline-secondary <?= $method === 'simple_count' ? 'active' : '' ?>" href="?id=<?= $projectId ?>&method=simple_count">Simple count</a>
      </div>
      <?php if ($progress['warning']): ?><div class="alert alert-warning small mt-2 mb-0 py-1"><?= e($progress['warning']) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat"><?= (int)$progress['completed_count'] ?> / <?= (int)$progress['active_count'] ?></div>
      <div class="text-muted small">Completed vs. open tasks</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat"><?= $plannedHours ?>h / <?= $actualHours ?>h</div>
      <div class="text-muted small">Planned vs. actual effort</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat text-warning"><?= (int)$stats['unplanned']['n'] ?></div>
      <div class="text-muted small">Unplanned tasks (<?= format_minutes((int)$stats['unplanned']['minutes']) ?>)</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="af-card">
      <h6>Tasks by status</h6>
      <canvas id="statusChart" height="180"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card">
      <h6>Tasks by assignee</h6>
      <canvas id="assigneeChart" height="180"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="af-card h-100">
      <h6>Overdue tasks</h6>
      <?php if (!$stats['overdue']): ?><p class="text-muted small mb-0">No overdue tasks.</p><?php endif; ?>
      <?php foreach ($stats['overdue'] as $t): ?>
        <div class="af-activity-item urgent"><a class="title text-reset text-decoration-none" href="my_tasks.php?activity=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
          <div class="small text-muted">Target was <?= e(format_datetime($t['target_completion_at'])) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card h-100">
      <h6>Upcoming deadlines</h6>
      <?php if (!$stats['upcoming']): ?><p class="text-muted small mb-0">No upcoming deadlines.</p><?php endif; ?>
      <?php foreach ($stats['upcoming'] as $t): ?>
        <div class="af-activity-item"><a class="title text-reset text-decoration-none" href="my_tasks.php?activity=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
          <div class="small text-muted">Due <?= e(format_datetime($t['target_completion_at'])) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="af-card h-100">
      <h6>Members</h6>
      <table class="table table-sm mb-0">
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= e($m['full_name']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= e(status_label($m['project_role'])) ?></span></td>
            <?php if ($canManage): ?><td class="text-end"><button class="btn btn-sm btn-link text-danger p-0" onclick="afProjectDetail.removeMember(<?= (int)$m['person_id'] ?>)">Remove</button></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="af-card h-100">
      <h6>Requesters generating work for this project</h6>
      <table class="table table-sm mb-0">
        <thead><tr><th>Requester</th><th>Tasks</th></tr></thead>
        <tbody>
        <?php foreach ($stats['requesters'] as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="af-card">
  <h6>Recent activity</h6>
  <?php if (!$recent): ?><p class="text-muted small mb-0">No recent changes recorded.</p><?php endif; ?>
  <ul class="list-unstyled small mb-0">
    <?php foreach ($recent as $r): ?>
      <li class="mb-1"><span class="text-muted"><?= e(format_datetime($r['created_at'])) ?></span> — <?= e($r['actor_name'] ?? 'System') ?> <?= e(str_replace('_', ' ', $r['action'])) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="editProjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="editProjectForm">
        <input type="hidden" name="id" value="<?= $projectId ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit project</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-8 mb-2"><label class="form-label">Project name *</label>
              <input class="form-control" name="name" required value="<?= e($project['name']) ?>">
            </div>
            <div class="col-md-4 mb-2"><label class="form-label">Code *</label>
              <input class="form-control" name="code" required value="<?= e($project['code']) ?>">
            </div>
          </div>
          <div class="mb-2"><label class="form-label">Description</label>
            <textarea class="form-control" name="description" id="editProjectDescription" rows="2"><?= e($project['description'] ?? '') ?></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label">Owner / Project Manager *</label>
              <select class="form-select" name="owner_id" required>
                <?php foreach ($people as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$project['owner_id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-2"><label class="form-label">Department</label>
              <select class="form-select" name="department_id">
                <option value="">—</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)($project['department_id'] ?? 0) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label">Start date</label>
              <input type="date" class="form-control" name="start_date" value="<?= e($project['start_date'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-2"><label class="form-label">Target completion</label>
              <input type="date" class="form-control" name="target_completion_date" value="<?= e($project['target_completion_date'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-2"><label class="form-label">Actual completion</label>
              <input type="date" class="form-control" name="actual_completion_date" value="<?= e($project['actual_completion_date'] ?? '') ?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label">Priority</label>
              <select class="form-select" name="priority">
                <?php foreach (ACTIVITY_PRIORITIES as $p): ?>
                  <option value="<?= $p ?>" <?= $p === $project['priority'] ? 'selected' : '' ?>><?= e(status_label($p)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mb-2"><label class="form-label">Status</label>
              <select class="form-select" name="status">
                <?php foreach ($projectStatuses as $s): ?>
                  <option value="<?= $s ?>" <?= $s === $project['status'] ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mb-2"><label class="form-label">Planned effort (hours)</label>
              <input type="number" step="0.5" min="0" class="form-control" name="planned_effort_hours" value="<?= e($project['planned_effort_hours'] ?? '') ?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label">Calendar color</label>
              <input type="color" class="form-control form-control-color" name="color" value="<?= e($project['color']) ?>">
            </div>
            <div class="col-md-8 mb-2 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="is_archived" id="editProjectArchived" value="1" <?= !empty($project['is_archived']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="editProjectArchived">Archived — hide from active project lists (still available in historical reports)</label>
              </div>
            </div>
          </div>
          <div class="mb-2"><label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2"><?= e($project['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="memberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="memberForm">
        <input type="hidden" name="project_id" value="<?= $projectId ?>">
        <div class="modal-header"><h5 class="modal-title">Add member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Person</label>
            <select class="form-select" name="person_id" required>
              <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Project role</label>
            <select class="form-select" name="project_role">
              <option value="project_manager">Project manager</option>
              <option value="contributor" selected>Contributor</option>
              <option value="reviewer">Reviewer</option>
              <option value="stakeholder">Stakeholder</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
window.AF_PROJECT = {
  id: <?= $projectId ?>,
  statusData: <?= json_encode(array_map(fn($r) => ['status' => status_label($r['status']), 'n' => (int)$r['n']], $stats['by_status'])) ?>,
  assigneeData: <?= json_encode(array_map(fn($r) => ['name' => $r['full_name'], 'n' => (int)$r['n']], $stats['by_assignee'])) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

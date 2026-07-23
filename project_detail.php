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
    deny(t('pd.no_access'));
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
$totalTaskCount = array_sum(array_column($stats['by_status'], 'n'));

$effort = $stats['effort'];
$plannedHours = round(((int)$effort['planned_minutes']) / 60, 1);
$actualHours = round(((int)$effort['actual_minutes']) / 60, 1);

$pageTitle = $project['name'];
$activeNav = 'projects';
$breadcrumbs = [['label' => t('projects.title'), 'url' => base_url('projects.php')], ['label' => $project['name']]];
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
    <div class="text-muted small mt-1"><?= e($project['code']) ?> · <?= e(t('pd.owner', ['name' => $project['owner_name']])) ?>
      <?= $project['start_date'] ? ' · ' . e(t('pd.start', ['date' => format_date($project['start_date'])])) : '' ?>
      <?= $project['target_completion_date'] ? ' · ' . e(t('pd.target', ['date' => format_date($project['target_completion_date'])])) : '' ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('project_board.php?id=' . $projectId)) ?>" class="btn btn-outline-primary"><i class="bi bi-kanban"></i> <?= e(t('pd.task_board')) ?></a>
    <?php if ($canManage): ?>
      <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProjectModal"><i class="bi bi-pencil-square"></i> <?= e(t('pd.edit_project')) ?></button>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memberModal"><i class="bi bi-person-plus"></i> <?= e(t('pd.add_member')) ?></button>
      <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal"><i class="bi bi-trash3"></i> <?= e(t('pd.delete_project')) ?></button>
    <?php endif; ?>
  </div>
</div>

<?php if ($project['description']): ?><div class="text-muted af-rich-text mb-3"><?= $project['description'] /* sanitized on write via sanitize_html() — safe to echo raw */ ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat"><?= (float)$progress['percent'] ?>%</div>
      <div class="text-muted small"><?= e(t('pd.progress_label', ['method' => $progress['method'] === 'duration_weighted' ? t('pd.duration_weighted') : t('pd.simple_count')])) ?></div>
      <div class="btn-group btn-group-sm mt-2">
        <a class="btn btn-outline-secondary <?= $method === 'duration_weighted' ? 'active' : '' ?>" href="?id=<?= $projectId ?>&method=duration_weighted"><?= e(t('pd.duration_weighted_btn')) ?></a>
        <a class="btn btn-outline-secondary <?= $method === 'simple_count' ? 'active' : '' ?>" href="?id=<?= $projectId ?>&method=simple_count"><?= e(t('pd.simple_count_btn')) ?></a>
      </div>
      <?php if ($progress['warning']): ?><div class="alert alert-warning small mt-2 mb-0 py-1"><?= e($progress['warning']) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat"><?= (int)$progress['completed_count'] ?> / <?= (int)$progress['active_count'] ?></div>
      <div class="text-muted small"><?= e(t('pd.completed_vs_open')) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat"><?= $plannedHours ?>h / <?= $actualHours ?>h</div>
      <div class="text-muted small"><?= e(t('pd.planned_vs_actual')) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="af-card text-center">
      <div class="af-stat text-warning"><?= (int)$stats['unplanned']['n'] ?></div>
      <div class="text-muted small"><?= e(t('pd.unplanned_tasks', ['time' => format_minutes((int)$stats['unplanned']['minutes'])])) ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="af-card">
      <h6><?= e(t('pd.chart_by_status')) ?></h6>
      <canvas id="statusChart" height="180"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card">
      <h6><?= e(t('pd.chart_by_assignee')) ?></h6>
      <canvas id="assigneeChart" height="180"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="af-card h-100">
      <h6><?= e(t('pd.overdue_tasks')) ?></h6>
      <?php if (!$stats['overdue']): ?><p class="text-muted small mb-0"><?= e(t('pd.no_overdue')) ?></p><?php endif; ?>
      <?php foreach ($stats['overdue'] as $t): ?>
        <div class="af-activity-item urgent"><a class="title text-reset text-decoration-none" href="my_tasks.php?activity=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
          <div class="small text-muted"><?= e(t('pd.target_was', ['date' => format_datetime($t['target_completion_at'])])) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card h-100">
      <h6><?= e(t('pd.upcoming_deadlines')) ?></h6>
      <?php if (!$stats['upcoming']): ?><p class="text-muted small mb-0"><?= e(t('pd.no_upcoming')) ?></p><?php endif; ?>
      <?php foreach ($stats['upcoming'] as $t): ?>
        <div class="af-activity-item"><a class="title text-reset text-decoration-none" href="my_tasks.php?activity=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
          <div class="small text-muted"><?= e(t('pd.due', ['date' => format_datetime($t['target_completion_at'])])) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="af-card h-100">
      <h6><?= e(t('pd.members')) ?></h6>
      <table class="table table-sm mb-0">
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= e($m['full_name']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= e(status_label($m['project_role'])) ?></span></td>
            <?php if ($canManage): ?><td class="text-end"><button class="btn btn-sm btn-link text-danger p-0" onclick="afProjectDetail.removeMember(<?= (int)$m['person_id'] ?>)"><?= e(t('pd.remove')) ?></button></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="af-card h-100">
      <h6><?= e(t('pd.requesters_heading')) ?></h6>
      <table class="table table-sm mb-0">
        <thead><tr><th><?= e(t('common.requester')) ?></th><th><?= e(t('pd.tasks')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($stats['requesters'] as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="af-card">
  <h6><?= e(t('pd.recent_activity')) ?></h6>
  <?php if (!$recent): ?><p class="text-muted small mb-0"><?= e(t('pd.no_recent_activity')) ?></p><?php endif; ?>
  <ul class="list-unstyled small mb-0">
    <?php foreach ($recent as $r): ?>
      <li class="mb-1"><span class="text-muted"><?= e(format_datetime($r['created_at'])) ?></span> — <?= e($r['actor_name'] ?? t('pd.system')) ?> <?= e(str_replace('_', ' ', $r['action'])) ?></li>
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
          <h5 class="modal-title"><?= e(t('pd.edit_project')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-8 mb-2"><label class="form-label"><?= e(t('projects.field_name')) ?></label>
              <input class="form-control" name="name" required value="<?= e($project['name']) ?>">
            </div>
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_code')) ?></label>
              <input class="form-control" name="code" required value="<?= e($project['code']) ?>">
            </div>
          </div>
          <div class="mb-2"><label class="form-label"><?= e(t('projects.field_description')) ?></label>
            <textarea class="form-control" name="description" id="editProjectDescription" rows="2"><?= e($project['description'] ?? '') ?></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('projects.field_owner')) ?></label>
              <select class="form-select" name="owner_id" required>
                <?php foreach ($people as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$project['owner_id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('pd.department')) ?></label>
              <select class="form-select" name="department_id">
                <option value="">—</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)($project['department_id'] ?? 0) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_start_date')) ?></label>
              <input type="date" class="form-control" name="start_date" value="<?= e($project['start_date'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_target_completion')) ?></label>
              <input type="date" class="form-control" name="target_completion_date" value="<?= e($project['target_completion_date'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('pd.actual_completion')) ?></label>
              <input type="date" class="form-control" name="actual_completion_date" value="<?= e($project['actual_completion_date'] ?? '') ?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_priority')) ?></label>
              <select class="form-select" name="priority">
                <?php foreach (ACTIVITY_PRIORITIES as $p): ?>
                  <option value="<?= $p ?>" <?= $p === $project['priority'] ? 'selected' : '' ?>><?= e(status_label($p)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_status')) ?></label>
              <select class="form-select" name="status">
                <?php foreach ($projectStatuses as $s): ?>
                  <option value="<?= $s ?>" <?= $s === $project['status'] ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_planned_effort')) ?></label>
              <input type="number" step="0.5" min="0" class="form-control" name="planned_effort_hours" value="<?= e($project['planned_effort_hours'] ?? '') ?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('projects.field_color')) ?></label>
              <input type="color" class="form-control form-control-color" name="color" value="<?= e($project['color']) ?>">
            </div>
            <div class="col-md-8 mb-2 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="is_archived" id="editProjectArchived" value="1" <?= !empty($project['is_archived']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="editProjectArchived"><?= e(t('pd.archived_label')) ?></label>
              </div>
            </div>
          </div>
          <div class="mb-2"><label class="form-label"><?= e(t('projects.field_notes')) ?></label>
            <textarea class="form-control" name="notes" rows="2"><?= e($project['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
          <button type="submit" class="btn btn-primary"><?= e(t('pd.save_changes')) ?></button>
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
        <div class="modal-header"><h5 class="modal-title"><?= e(t('pd.add_member')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label"><?= e(t('pd.person')) ?></label>
            <select class="form-select" name="person_id" required>
              <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label"><?= e(t('pd.project_role')) ?></label>
            <select class="form-select" name="project_role">
              <option value="project_manager"><?= e(t('pd.role_project_manager')) ?></option>
              <option value="contributor" selected><?= e(t('pd.role_contributor')) ?></option>
              <option value="reviewer"><?= e(t('pd.role_reviewer')) ?></option>
              <option value="stakeholder"><?= e(t('pd.role_stakeholder')) ?></option>
              <option value="viewer"><?= e(t('pd.role_viewer')) ?></option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.add')) ?></button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteProjectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('pd.delete_project')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger">
          <strong><?= e(t('pd.delete_cannot_undo')) ?></strong> <?= e(t('pd.delete_warning', ['name' => $project['name']])) ?>
          <ul class="mb-0 mt-1">
            <li><?= e(t('pd.delete_task_count', ['count' => (int)$totalTaskCount])) ?></li>
            <li><?= e(t('pd.delete_member_count', ['count' => count($members)])) ?></li>
          </ul>
        </div>
        <label class="form-label"><?= e(t('pd.type_to_confirm', ['name' => $project['name']])) ?></label>
        <input type="text" class="form-control" id="deleteProjectConfirmInput" autocomplete="off">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
        <button type="button" class="btn btn-danger" id="deleteProjectConfirmBtn" disabled
          onclick="afProjectDetail.deleteProject(<?= $projectId ?>, <?= json_encode($project['name']) ?>)">
          <i class="bi bi-trash3"></i> <?= e(t('pd.delete_permanently')) ?>
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
window.AF_PROJECT = {
  id: <?= $projectId ?>,
  name: <?= json_encode($project['name']) ?>,
  statusData: <?= json_encode(array_map(fn($r) => ['status' => status_label($r['status']), 'n' => (int)$r['n']], $stats['by_status'])) ?>,
  assigneeData: <?= json_encode(array_map(fn($r) => ['name' => $r['full_name'], 'n' => (int)$r['n']], $stats['by_assignee'])) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

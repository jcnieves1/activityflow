<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$filters = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-90 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'department_id' => $_GET['department_id'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
];
if (!is_admin() && !is_pm() && !user_has_role(ROLE_VIEWER)) {
    $filters['employee_id'] = current_person_id() ?: -1;
}
$rows = requester_analytics($filters);
$departments = department_list();
$projects = list_projects(['is_archived' => 0]);

function top(array $rows, string $key, int $n = 5, bool $asc = false): array
{
    $sorted = $rows;
    usort($sorted, fn($a, $b) => $asc ? (($a[$key] ?? PHP_INT_MAX) <=> ($b[$key] ?? PHP_INT_MAX)) : (($b[$key] ?? 0) <=> ($a[$key] ?? 0)));
    return array_slice(array_filter($sorted, fn($r) => $r[$key] !== null), 0, $n);
}

$pageTitle = 'Requester Analytics';
$activeNav = 'requesters';
$breadcrumbs = [['label' => 'Requester Analytics']];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-1">Requester Analytics</h4>
<p class="text-muted small">Analytical view of who requests work and how much of it is unplanned. Figures always include the date range and sample size used.</p>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-3"><label class="form-label small mb-0">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filters['date_from']) ?>"></div>
  <div class="col-md-3"><label class="form-label small mb-0">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filters['date_to']) ?>"></div>
  <div class="col-md-3"><label class="form-label small mb-0">Department</label><select class="form-select form-select-sm" name="department_id"><option value="">All</option>
    <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$filters['department_id'] === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label small mb-0">Project</label><select class="form-select form-select-sm" name="project_id"><option value="">All</option>
    <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)$filters['project_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-12"><button class="btn btn-outline-secondary btn-sm">Apply</button></div>
</form>

<div class="alert alert-light border small">
  Showing <strong><?= count($rows) ?></strong> requester(s) with activity between <strong><?= e($filters['date_from']) ?></strong> and <strong><?= e($filters['date_to']) ?></strong>.
  Small sample sizes can make percentages misleading — check the record count before drawing conclusions.
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="af-card h-100"><h6>Most unplanned requests</h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'unplanned_tasks') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (int)$r['unplanned_tasks'] ?></td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-4"><div class="af-card h-100"><h6>Most unplanned hours</h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'estimated_hours') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (float)$r['estimated_hours'] ?>h</td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-4"><div class="af-card h-100"><h6>Most urgent interruptions</h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'urgent_tasks') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (int)$r['urgent_tasks'] ?></td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-6"><div class="af-card h-100"><h6>Most projects affected</h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'projects_affected') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (int)$r['projects_affected'] ?></td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-6"><div class="af-card h-100"><h6>Shortest average notice period</h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'avg_notice_minutes', 5, true) as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= format_minutes($r['avg_notice_minutes'] !== null ? (int)$r['avg_notice_minutes'] : null) ?></td></tr><?php endforeach; ?></table>
  </div></div>
</div>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr>
        <th>Requester</th><th>Total</th><th>Planned</th><th>Unplanned</th><th>% unplanned</th>
        <th>Est. hours</th><th>Actual hours</th><th>Employees affected</th><th>Projects affected</th>
        <th>No project</th><th>Urgent</th><th>Avg notice</th><th>Avg interruption</th><th>Delayed activities</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['requester_name']) ?></td>
          <td><?= (int)$r['total_tasks'] ?></td>
          <td><?= (int)$r['planned_tasks'] ?></td>
          <td><?= (int)$r['unplanned_tasks'] ?></td>
          <td><?= (float)$r['pct_unplanned'] ?>%</td>
          <td><?= (float)$r['estimated_hours'] ?>h</td>
          <td><?= (float)$r['actual_hours'] ?>h</td>
          <td><?= (int)$r['employees_affected'] ?></td>
          <td><?= (int)$r['projects_affected'] ?></td>
          <td><?= (int)$r['tasks_without_project'] ?></td>
          <td><?= (int)$r['urgent_tasks'] ?></td>
          <td><?= format_minutes($r['avg_notice_minutes'] !== null ? (int)$r['avg_notice_minutes'] : null) ?></td>
          <td><?= $r['avg_interruption_minutes'] !== null ? format_minutes((int)$r['avg_interruption_minutes']) : '—' ?></td>
          <td><?= (int)$r['delayed_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="14"><div class="af-empty"><i class="bi bi-graph-up-arrow"></i>No requester activity in this range.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$personId = current_person_id();
$personal = $personId ? personal_dashboard_data($personId) : null;

$isManager = is_admin() || is_pm();
$manager = null;
if ($isManager) {
    $manager = manager_dashboard_data([
        'employee_id' => $_GET['employee_id'] ?? '',
        'project_id' => $_GET['project_id'] ?? '',
        'requester_id' => $_GET['requester_id'] ?? '',
        'department_id' => $_GET['department_id'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
    ]);
}
$people = list_people(['is_active' => 1]);
$projects = list_projects(['is_archived' => 0]);
$departments = department_list();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$breadcrumbs = [['label' => 'Dashboard']];
$pageScripts = ['https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js', base_url('assets/js/dashboard.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-3">Welcome back, <?= e(explode(' ', current_user()['full_name'])[0]) ?></h4>

<?php if ($personal): ?>
<div class="row g-3 mb-2">
  <div class="col-6 col-md-3"><div class="af-card text-center"><div class="af-stat text-primary"><?= count($personal['todayPlanned']) ?></div><div class="text-muted small">Planned today</div></div></div>
  <div class="col-6 col-md-3"><div class="af-card text-center"><div class="af-stat" style="color:var(--af-unplanned)"><?= count($personal['todayUnplanned']) ?></div><div class="text-muted small">Unplanned today</div></div></div>
  <div class="col-6 col-md-3"><div class="af-card text-center"><div class="af-stat text-success"><?= $personal['completionRate'] ?>%</div><div class="text-muted small">Daily completion rate</div></div></div>
  <div class="col-6 col-md-3"><div class="af-card text-center">
    <?php if ($personal['timer']): ?><div class="fw-semibold text-truncate"><i class="bi bi-stopwatch text-success"></i> <?= e($personal['timer']['activity_title']) ?></div><div class="text-muted small">Active timer</div>
    <?php else: ?><div class="text-muted small">No active timer</div><?php endif; ?>
  </div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="af-card"><h6>Planned vs. unplanned time (today)</h6><canvas id="plannedUnplannedChart" height="200"></canvas></div>
  </div>
  <div class="col-lg-4">
    <div class="af-card"><h6>Weekly planned vs. actual effort</h6><canvas id="weeklyChart" height="200"></canvas></div>
  </div>
  <div class="col-lg-4">
    <div class="af-card"><h6>Unplanned tasks by source</h6><canvas id="sourceChart" height="200"></canvas></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="af-card h-100"><h6>Overdue tasks</h6>
      <?php foreach ($personal['overdue'] as $t): ?><div class="af-activity-item urgent"><?= e($t['title']) ?><div class="small text-muted">Due <?= e(format_datetime($t['target_completion_at'])) ?></div></div><?php endforeach; ?>
      <?php if (!$personal['overdue']): ?><p class="text-muted small mb-0">No overdue tasks.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="af-card h-100"><h6>Upcoming deadlines</h6>
      <?php foreach ($personal['upcoming'] as $t): ?><div class="af-activity-item"><?= e($t['title']) ?><div class="small text-muted">Due <?= e(format_datetime($t['target_completion_at'])) ?></div></div><?php endforeach; ?>
      <?php if (!$personal['upcoming']): ?><p class="text-muted small mb-0">Nothing due soon.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="af-card h-100"><h6>Most frequent requesters</h6>
      <table class="table table-sm mb-0"><?php foreach ($personal['topRequesters'] as $r): ?><tr><td><?= e($r['full_name']) ?></td><td class="text-end"><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table>
      <?php if (!$personal['topRequesters']): ?><p class="text-muted small mb-0">No unplanned requests recorded.</p><?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="af-card h-100"><h6>Most interrupted projects</h6>
      <table class="table table-sm mb-0"><?php foreach ($personal['interruptedProjects'] as $r): ?><tr><td><?= e($r['name']) ?></td><td class="text-end"><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table>
      <?php if (!$personal['interruptedProjects']): ?><p class="text-muted small mb-0">No project interruptions recorded.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card h-100"><h6>Recent project progress</h6>
      <?php foreach ($personal['myProjects'] as $p): ?>
        <div class="d-flex justify-content-between small mb-1"><span><?= e($p['name']) ?></span><span><?= (float)$p['progress']['percent'] ?>%</span></div>
        <div class="progress mb-2" style="height:6px;"><div class="progress-bar" style="width:<?= (float)$p['progress']['percent'] ?>%;background:<?= e($p['color']) ?>"></div></div>
      <?php endforeach; ?>
      <?php if (!$personal['myProjects']): ?><p class="text-muted small mb-0">You're not a member of any active projects.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
  <div class="af-empty"><i class="bi bi-person-x"></i>Your login isn't linked to a person record yet.</div>
<?php endif; ?>

<?php if ($isManager): ?>
<hr class="my-4">
<h5 class="mb-3">Manager Dashboard</h5>
<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-2"><select class="form-select form-select-sm" name="employee_id"><option value="">All employees</option>
    <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['employee_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="project_id"><option value="">All projects</option>
    <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['project_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="requester_id"><option value="">All requesters</option>
    <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['requester_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="department_id"><option value="">All departments</option>
    <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)($_GET['department_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-secondary btn-sm w-100">Filter</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="af-card text-center"><div class="af-stat text-danger"><?= (int)$manager['overdueCount'] ?></div><div class="text-muted small">Overdue tasks</div></div></div>
  <div class="col-md-3"><div class="af-card text-center"><div class="af-stat"><?= count($manager['lateProjects']) ?></div><div class="text-muted small">Late projects</div></div></div>
  <div class="col-md-3"><div class="af-card text-center"><div class="af-stat"><?= round(((int)$manager['estVsActual']['est'])/60,1) ?>h</div><div class="text-muted small">Estimated hours</div></div></div>
  <div class="col-md-3"><div class="af-card text-center"><div class="af-stat"><?= round(((int)$manager['estVsActual']['actual'])/60,1) ?>h</div><div class="text-muted small">Actual hours logged</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="af-card"><h6>Planned vs. unplanned work by employee</h6><canvas id="employeeWorkloadChart" height="220"></canvas></div></div>
  <div class="col-lg-6"><div class="af-card"><h6>Unplanned work by requester</h6><canvas id="requesterUnplannedChart" height="220"></canvas></div></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="af-card"><h6>Unplanned work by department</h6><canvas id="departmentUnplannedChart" height="220"></canvas></div></div>
  <div class="col-lg-6"><div class="af-card"><h6>Interruption frequency &amp; average duration</h6>
    <table class="table table-sm"><thead><tr><th>Employee</th><th>Interruptions</th><th>Avg. minutes lost</th></tr></thead><tbody>
    <?php foreach ($manager['interruptionStats'] as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= (int)$r['n'] ?></td><td><?= round((float)$r['avg_lost'],1) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div></div>
</div>

<div class="af-card mb-3">
  <h6>Project progress</h6>
  <div class="row g-2">
    <?php foreach ($manager['projects'] as $p): ?>
      <div class="col-md-4">
        <div class="d-flex justify-content-between small"><span><?= e($p['name']) ?></span><span><?= (float)$p['progress']['percent'] ?>%</span></div>
        <div class="progress mb-2" style="height:6px;"><div class="progress-bar" style="width:<?= (float)$p['progress']['percent'] ?>%;background:<?= e($p['color']) ?>"></div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="af-card">
  <h6>Late projects</h6>
  <?php if (!$manager['lateProjects']): ?><p class="text-muted small mb-0">No projects are past their target date.</p><?php endif; ?>
  <?php foreach ($manager['lateProjects'] as $p): ?>
    <div class="af-activity-item urgent"><?= e($p['name']) ?><div class="small text-muted">Target was <?= e(format_date($p['target_completion_date'])) ?></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
window.AF_DASHBOARD = {
  plannedMinutes: <?= (int)($personal['hours']['planned_minutes'] ?? 0) ?>,
  unplannedMinutes: <?= (int)($personal['hours']['unplanned_minutes'] ?? 0) ?>,
  weekly: <?= json_encode($personal['weekly'] ?? []) ?>,
  bySource: <?= json_encode(array_map(fn($r) => ['label' => request_channel_label($r['request_channel']), 'n' => (int)$r['n']], $personal['bySource'] ?? [])) ?>,
  <?php if ($isManager): ?>
  employeeWorkload: <?= json_encode($manager['workloadByEmployee']) ?>,
  requesterUnplanned: <?= json_encode($manager['unplannedByRequester']) ?>,
  departmentUnplanned: <?= json_encode(array_map(fn($r) => ['name' => $r['name'] ?? 'Unassigned', 'n' => (int)$r['n']], $manager['unplannedByDepartment'])) ?>
  <?php endif; ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

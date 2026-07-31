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
if (!has_broad_project_visibility()) {
    $filters['restrict_to_person_id'] = current_person_id() ?: -1;
}
$rows = requester_analytics($filters);
$departments = department_list();
$projects = filter_visible_projects(list_projects(['is_archived' => 0]));

function top(array $rows, string $key, int $n = 5, bool $asc = false): array
{
    $sorted = $rows;
    usort($sorted, fn($a, $b) => $asc ? (($a[$key] ?? PHP_INT_MAX) <=> ($b[$key] ?? PHP_INT_MAX)) : (($b[$key] ?? 0) <=> ($a[$key] ?? 0)));
    return array_slice(array_filter($sorted, fn($r) => $r[$key] !== null), 0, $n);
}

$pageTitle = t('nav.requesters');
$activeNav = 'requesters';
$breadcrumbs = [['label' => t('nav.requesters')]];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-1"><?= e(t('nav.requesters')) ?></h4>
<p class="text-muted small"><?= e(t('ra.subtitle')) ?></p>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('ra.from')) ?></label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filters['date_from']) ?>"></div>
  <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('ra.to')) ?></label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filters['date_to']) ?>"></div>
  <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('ra.department')) ?></label><select class="form-select form-select-sm" name="department_id"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$filters['department_id'] === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('ra.project')) ?></label><select class="form-select form-select-sm" name="project_id"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)$filters['project_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-12"><button class="btn btn-outline-secondary btn-sm"><?= e(t('ra.apply')) ?></button></div>
</form>

<div class="alert alert-light border small">
  <?= e(t('ra.showing', ['count' => count($rows), 'from' => $filters['date_from'], 'to' => $filters['date_to']])) ?>
  <?= e(t('ra.sample_warning')) ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="af-card h-100"><h6><?= e(t('ra.most_unplanned_requests')) ?></h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'unplanned_tasks') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (int)$r['unplanned_tasks'] ?></td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-4"><div class="af-card h-100"><h6><?= e(t('ra.most_unplanned_hours')) ?></h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'estimated_hours') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (float)$r['estimated_hours'] ?>h</td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-4"><div class="af-card h-100"><h6><?= e(t('ra.most_urgent_interruptions')) ?></h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'urgent_tasks') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (int)$r['urgent_tasks'] ?></td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-6"><div class="af-card h-100"><h6><?= e(t('ra.most_projects_affected')) ?></h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'projects_affected') as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= (int)$r['projects_affected'] ?></td></tr><?php endforeach; ?></table>
  </div></div>
  <div class="col-lg-6"><div class="af-card h-100"><h6><?= e(t('ra.shortest_notice')) ?></h6>
    <table class="table table-sm mb-0"><?php foreach (top($rows, 'avg_notice_minutes', 5, true) as $r): ?><tr><td><?= e($r['requester_name']) ?></td><td class="text-end"><?= format_minutes($r['avg_notice_minutes'] !== null ? (int)$r['avg_notice_minutes'] : null) ?></td></tr><?php endforeach; ?></table>
  </div></div>
</div>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr>
        <th><?= e(t('ra.col_requester')) ?></th><th><?= e(t('ra.col_total')) ?></th><th><?= e(t('ra.col_planned')) ?></th><th><?= e(t('ra.col_unplanned')) ?></th><th><?= e(t('ra.col_pct_unplanned')) ?></th>
        <th><?= e(t('ra.col_est_hours')) ?></th><th><?= e(t('ra.col_actual_hours')) ?></th><th><?= e(t('ra.col_employees_affected')) ?></th><th><?= e(t('ra.col_projects_affected')) ?></th>
        <th><?= e(t('ra.col_no_project')) ?></th><th><?= e(t('ra.col_urgent')) ?></th><th><?= e(t('ra.col_avg_notice')) ?></th><th><?= e(t('ra.col_avg_interruption')) ?></th><th><?= e(t('ra.col_delayed')) ?></th>
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
      <?php if (!$rows): ?><tr><td colspan="14"><div class="af-empty"><i class="bi bi-graph-up-arrow"></i><?= e(t('ra.empty')) ?></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

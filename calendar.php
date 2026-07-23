<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$projects = list_projects(['is_archived' => 0]);

$pageTitle = 'Calendar';
$activeNav = 'calendar';
$breadcrumbs = [['label' => 'Calendar']];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.js',
    base_url('assets/js/activities.js'),
    base_url('assets/js/calendar.js'),
];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0">Calendar</h4>
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" id="calAssignee" style="width:auto">
      <option value="">All employees</option>
      <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $p['id'] == current_person_id() ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" id="calProject" style="width:auto">
      <option value="">All projects</option>
      <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" onclick="afActivities.openCreate({})"><i class="bi bi-plus-lg"></i> New</button>
  </div>
</div>
<div class="af-card">
  <div class="mb-2 small text-muted">
    <span class="badge" style="background:#4361ee">&nbsp;</span> Planned &nbsp;
    <span class="badge" style="background:#f4a261">&nbsp;</span> Unplanned &nbsp;
    <span class="badge" style="background:#e63946">&nbsp;</span> Urgent &nbsp;
    <span class="badge" style="background:#2a9d8f">&nbsp;</span> Completed &nbsp;
    <span class="badge" style="background:#6c757d">&nbsp;</span> Blocked
  </div>
  <div id="calendarEl"></div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

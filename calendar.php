<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$projects = list_projects(['is_archived' => 0]);

$pageTitle = t('nav.calendar');
$activeNav = 'calendar';
$breadcrumbs = [['label' => t('nav.calendar')]];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.js',
    base_url('assets/js/activities.js'),
    base_url('assets/js/calendar.js'),
];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><?= e(t('nav.calendar')) ?></h4>
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" id="calAssignee" style="width:auto">
      <option value=""><?= e(t('tasks.all_employees')) ?></option>
      <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $p['id'] == current_person_id() ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" id="calProject" style="width:auto">
      <option value=""><?= e(t('tasks.all_projects')) ?></option>
      <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" onclick="afActivities.openCreate({})"><i class="bi bi-plus-lg"></i> <?= e(t('calendar.new')) ?></button>
  </div>
</div>
<div class="af-card">
  <div class="mb-2 small text-muted">
    <span class="badge" style="background:#4361ee">&nbsp;</span> <?= e(t('calendar.legend_planned')) ?> &nbsp;
    <span class="badge" style="background:#f4a261">&nbsp;</span> <?= e(t('calendar.legend_unplanned')) ?> &nbsp;
    <span class="badge" style="background:#e63946">&nbsp;</span> <?= e(t('calendar.legend_urgent')) ?> &nbsp;
    <span class="badge" style="background:#2a9d8f">&nbsp;</span> <?= e(t('calendar.legend_completed')) ?> &nbsp;
    <span class="badge" style="background:#6c757d">&nbsp;</span> <?= e(t('calendar.legend_blocked')) ?>
  </div>
  <div id="calendarEl"></div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

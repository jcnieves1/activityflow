<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$projects = filter_visible_projects(list_projects(['is_archived' => 0]));

$pageTitle = t('nav.calendar');
$activeNav = 'calendar';
$breadcrumbs = [['label' => t('nav.calendar')]];
$pageStyles = ['https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css'];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js',
    base_url('assets/js/activities.js'),
    base_url('assets/js/calendar.js'),
];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><?= e(t('nav.calendar')) ?></h4>
  <div class="d-flex gap-2">
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="calAssigneeBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi bi-people"></i> <span id="calAssigneeLabel"><?= e(t('tasks.all_employees')) ?></span>
      </button>
      <div class="dropdown-menu p-3" style="min-width:240px;max-height:340px;overflow-y:auto;">
        <div class="form-check mb-2 border-bottom pb-2">
          <input class="form-check-input" type="checkbox" id="calAssigneeAll" <?= !current_person_id() ? 'checked' : '' ?>>
          <label class="form-check-label fw-semibold" for="calAssigneeAll"><?= e(t('tasks.all_employees')) ?></label>
        </div>
        <?php foreach ($people as $p): ?>
          <div class="form-check">
            <input class="form-check-input cal-assignee-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" id="calAssignee<?= (int)$p['id'] ?>" <?= $p['id'] == current_person_id() ? 'checked' : '' ?>>
            <label class="form-check-label" for="calAssignee<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="calProjectBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi bi-kanban"></i> <span id="calProjectLabel"><?= e(t('tasks.all_projects')) ?></span>
      </button>
      <div class="dropdown-menu p-3" style="min-width:240px;max-height:340px;overflow-y:auto;">
        <div class="form-check mb-2 border-bottom pb-2">
          <input class="form-check-input" type="checkbox" id="calProjectAll" checked>
          <label class="form-check-label fw-semibold" for="calProjectAll"><?= e(t('tasks.all_projects')) ?></label>
        </div>
        <?php foreach ($projects as $p): ?>
          <div class="form-check">
            <input class="form-check-input cal-project-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" id="calProject<?= (int)$p['id'] ?>">
            <label class="form-check-label" for="calProject<?= (int)$p['id'] ?>"><?= e($p['name']) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
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

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$personId = current_person_id();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday = $date === date('Y-m-d');

$planned = $personId ? my_day_activities($personId, $date) : [];
$unplanned = $personId ? my_day_unplanned($personId, $date) : [];
$backlog = $personId ? my_day_backlog($personId) : [];
$completed = $personId ? my_day_completed($personId, $date) : [];
$carriedOver = $personId ? my_day_carried_over($personId, $date) : [];
$hours = $personId ? my_day_hours_summary($personId, $date) : ['planned_minutes' => 0, 'unplanned_minutes' => 0];
$activeTimer = $personId ? active_timer_for($personId) : null;

$capacityMinutes = 480; // 8-hour workday, used only for the remaining-capacity indicator
$usedMinutes = $hours['planned_minutes'] + $hours['unplanned_minutes'];
$remaining = max(0, $capacityMinutes - $usedMinutes);

$pageTitle = t('nav.my_day');
$activeNav = 'my_day';
$breadcrumbs = [['label' => t('nav.my_day')]];
$pageScripts = [base_url('assets/js/activities.js'), base_url('assets/js/my_day.js')];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="?date=<?= $prevDate ?>"><i class="bi bi-chevron-left"></i></a>
    <h5 class="mb-0"><?= e(date('l, F j, Y', strtotime($date))) ?> <?= $isToday ? '<span class="badge bg-primary">' . e(t('myday.today')) . '</span>' : '' ?></h5>
    <a class="btn btn-outline-secondary btn-sm" href="?date=<?= $nextDate ?>"><i class="bi bi-chevron-right"></i></a>
    <?php if (!$isToday): ?><a class="btn btn-link btn-sm" href="?date=<?= date('Y-m-d') ?>"><?= e(t('myday.jump_to_today')) ?></a><?php endif; ?>
  </div>
  <button class="btn btn-primary" onclick="afActivities.openCreate({assignee_id: <?= (int)$personId ?>, planned_start_at: '<?= $date ?>T09:00'})"><i class="bi bi-plus-lg"></i> <?= e(t('myday.plan_activity')) ?></button>
</div>

<?php if (!$personId): ?>
  <div class="af-empty"><i class="bi bi-person-x"></i><?= e(t('tasks.no_person_linked')) ?></div>
<?php else: ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="af-card text-center"><div class="af-stat text-primary"><?= format_minutes($hours['planned_minutes']) ?></div><div class="text-muted small"><?= e(t('myday.planned')) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="af-card text-center"><div class="af-stat" style="color:var(--af-unplanned)"><?= format_minutes($hours['unplanned_minutes']) ?></div><div class="text-muted small"><?= e(t('myday.unplanned')) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="af-card text-center"><div class="af-stat"><?= format_minutes($remaining) ?></div><div class="text-muted small"><?= e(t('myday.remaining_capacity')) ?></div></div></div>
  <div class="col-6 col-md-3">
    <div class="af-card text-center">
      <?php if ($activeTimer): ?>
        <div class="fw-semibold text-truncate"><i class="bi bi-stopwatch text-success"></i> <?= e($activeTimer['activity_title']) ?></div>
        <div class="text-muted small"><?= e(t('myday.running_since', ['time' => format_datetime($activeTimer['started_at'], 'g:i A')])) ?></div>
        <button class="btn btn-sm btn-outline-warning mt-1" onclick="afActivities.stopTimer()"><?= e(t('myday.pause_stop')) ?></button>
      <?php else: ?>
        <div class="text-muted small"><?= e(t('myday.no_active_timer')) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($carriedOver): ?>
<div class="af-card mb-3">
  <h6><i class="bi bi-arrow-return-right"></i> <?= e(t('myday.carried_over')) ?></h6>
  <?php foreach ($carriedOver as $a): ?>
    <div class="af-activity-item <?= $a['status'] === 'blocked' ? 'blocked' : '' ?>">
      <div class="d-flex justify-content-between align-items-center">
        <div><span class="title"><?= e($a['title']) ?></span> <span class="small text-muted"><?= e(t('myday.was_due', ['date' => format_datetime($a['planned_start_at'])])) ?></span></div>
        <button class="btn btn-sm btn-outline-primary" onclick="afMyDay.copyToToday(<?= (int)$a['id'] ?>)"><?= e(t('myday.move_to_today')) ?></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="af-card h-100">
      <h6 class="text-primary"><i class="bi bi-calendar-check"></i> <?= e(t('myday.planned_activities')) ?></h6>
      <div id="plannedList" class="af-dropzone" data-list="planned">
        <?php foreach ($planned as $a): ?>
          <div class="af-activity-item <?= $a['status'] === 'completed' ? 'completed' : ($a['status'] === 'blocked' ? 'blocked' : '') ?>" draggable="true" data-id="<?= (int)$a['id'] ?>">
            <div class="d-flex justify-content-between">
              <span class="title" role="button" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)"><?= e($a['title']) ?></span>
              <span class="badge <?= status_badge_class($a['status']) ?>"><?= e(status_label($a['status'])) ?></span>
            </div>
            <div class="small text-muted"><?= $a['planned_start_at'] ? e(format_datetime($a['planned_start_at'], 'g:i A')) : e(t('myday.unscheduled')) ?> · <?= format_minutes((int)$a['estimated_minutes']) ?><?= $a['project_name'] ? ' · ' . e($a['project_name']) : '' ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$planned): ?><div class="af-empty py-3"><i class="bi bi-calendar-check"></i><?= e(t('myday.nothing_planned')) ?></div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="af-card h-100">
      <h6 style="color:var(--af-unplanned)"><i class="bi bi-lightning-fill"></i> <?= e(t('myday.unplanned_adhoc')) ?></h6>
      <?php foreach ($unplanned as $a): ?>
        <div class="af-activity-item <?= $a['priority'] === 'urgent' ? 'urgent' : 'unplanned' ?>" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)" role="button">
          <div class="d-flex justify-content-between">
            <span class="title"><?= e($a['title']) ?></span>
            <span class="badge <?= status_badge_class($a['status']) ?>"><?= e(status_label($a['status'])) ?></span>
          </div>
          <div class="small text-muted"><?= e(t('myday.requested_by_at', ['name' => $a['requester_name'], 'time' => format_datetime($a['requested_at'], 'g:i A')])) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$unplanned): ?><div class="af-empty py-3"><i class="bi bi-lightning"></i><?= e(t('myday.no_unplanned_today')) ?></div><?php endif; ?>
      <button class="btn btn-sm btn-warning w-100 mt-2" data-bs-toggle="modal" data-bs-target="#quickAddModal"><i class="bi bi-lightning-fill"></i> <?= e(t('quickadd.title')) ?></button>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="af-card h-100 mb-3">
      <h6><i class="bi bi-inbox"></i> <?= e(t('myday.backlog')) ?></h6>
      <?php foreach ($backlog as $a): ?>
        <div class="af-activity-item" data-id="<?= (int)$a['id'] ?>">
          <div class="title" role="button" onclick="afActivities.openEdit(<?= (int)$a['id'] ?>)"><?= e($a['title']) ?></div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="badge <?= priority_badge_class($a['priority']) ?>"><?= e(status_label($a['priority'])) ?></span>
            <button class="btn btn-sm btn-link p-0" onclick="afMyDay.scheduleToday(<?= (int)$a['id'] ?>)"><?= e(t('myday.schedule_today')) ?></button>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$backlog): ?><div class="af-empty py-3"><i class="bi bi-inbox"></i><?= e(t('myday.backlog_empty')) ?></div><?php endif; ?>
    </div>

    <div class="af-card">
      <h6 class="text-success"><i class="bi bi-check-circle"></i> <?= e(t('myday.completed_today')) ?></h6>
      <?php foreach ($completed as $a): ?>
        <div class="af-activity-item completed">
          <div class="title"><?= e($a['title']) ?></div>
          <div class="small text-muted"><?= e(t('myday.finished_at', ['time' => format_datetime($a['actual_completion_at'], 'g:i A')])) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$completed): ?><div class="af-empty py-3"><i class="bi bi-check-circle"></i><?= e(t('myday.nothing_completed')) ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<script>window.AF_MYDAY_DATE = <?= json_encode($date) ?>;</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

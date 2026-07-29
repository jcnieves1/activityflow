<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (!can_view_workload()) {
    deny(t('workload.no_access'));
}

$people = list_people(['is_active' => 1]);
$statuses = list_task_statuses();

$pageTitle = t('nav.workload');
$activeNav = 'workload';
$breadcrumbs = [['label' => t('nav.workload')]];
$pageStyles = ['https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css'];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js',
    base_url('assets/js/activities.js'),
    base_url('assets/js/workload.js'),
];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="mb-3">
  <h4 class="mb-0"><?= e(t('workload.title')) ?></h4>
  <p class="text-muted small mb-0"><?= e(t('workload.subtitle')) ?></p>
</div>

<div class="af-card mb-3">
  <form id="workloadFilterForm" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('workload.field_people')) ?></label>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="wlPeopleBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-people"></i> <span id="wlPeopleLabel"><?= e(t('board.all_team_members')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:260px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="wlPeopleAll" checked>
            <label class="form-check-label fw-semibold" for="wlPeopleAll"><?= e(t('board.all_team_members')) ?></label>
          </div>
          <?php if (!$people): ?>
            <div class="text-muted small"><?= e(t('board.no_members_yet')) ?></div>
          <?php endif; ?>
          <?php foreach ($people as $p): ?>
            <div class="form-check">
              <input class="form-check-input wl-person-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" id="wlPerson<?= (int)$p['id'] ?>">
              <label class="form-check-label" for="wlPerson<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('workload.field_statuses')) ?></label>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="wlStatusBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-flag"></i> <span id="wlStatusLabel"><?= e(t('board.all_statuses')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:220px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="wlStatusAll" checked>
            <label class="form-check-label fw-semibold" for="wlStatusAll"><?= e(t('board.all_statuses')) ?></label>
          </div>
          <?php foreach ($statuses as $st): ?>
            <div class="form-check">
              <input class="form-check-input wl-status-checkbox" type="checkbox" value="<?= e($st['slug']) ?>" id="wlStatus<?= e($st['slug']) ?>">
              <label class="form-check-label" for="wlStatus<?= e($st['slug']) ?>"><?= e($st['label']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('workload.field_date_from')) ?></label>
      <input type="date" class="form-control" id="wlDateFrom">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('workload.field_date_to')) ?></label>
      <input type="date" class="form-control" id="wlDateTo">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('workload.field_issue')) ?></label>
      <select class="form-select" id="wlIssueFilter">
        <option value=""><?= e(t('common.all')) ?></option>
        <option value="1"><?= e(t('tasks.issues_only')) ?></option>
        <option value="0"><?= e(t('tasks.non_issues_only')) ?></option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('workload.field_sort')) ?></label>
      <select class="form-select" id="wlSortOrder">
        <option value="asc"><?= e(t('workload.sort_least_busy')) ?></option>
        <option value="desc"><?= e(t('workload.sort_most_busy')) ?></option>
      </select>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> <?= e(t('workload.run')) ?></button>
    </div>
  </form>
</div>

<div id="workloadResults"></div>
<div class="af-empty d-none" id="workloadEmpty"><i class="bi bi-inboxes"></i><?= e(t('workload.no_people')) ?></div>

<script>
window.AF_I18N_WORKLOAD = {
  taskSingular: <?= json_encode(t('workload.task_singular')) ?>,
  taskPlural: <?= json_encode(t('workload.task_plural')) ?>,
  noTasks: <?= json_encode(t('workload.no_tasks_in_range')) ?>,
  open: <?= json_encode(t('common.open')) ?>,
  noProject: <?= json_encode(t('tasks.no_project')) ?>,
  issueBadge: <?= json_encode(t('workload.issue_badge')) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

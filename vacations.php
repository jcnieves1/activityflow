<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$personId = current_person_id();
$currentPersonName = '';
foreach ($people as $p) {
    if ((int)$p['id'] === (int)$personId) {
        $currentPersonName = $p['full_name'];
        break;
    }
}

$pageTitle = t('nav.vacations');
$activeNav = 'vacations';
$breadcrumbs = [['label' => t('nav.vacations')]];
$pageStyles = ['https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css'];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js',
    base_url('assets/js/activities.js'),
    base_url('assets/js/vacations.js'),
];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('vacations.title')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('vacations.subtitle')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="vacPersonBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi bi-people"></i> <span id="vacPersonLabel"><?= e(t('vacations.all_people')) ?></span>
      </button>
      <div class="dropdown-menu p-3" style="min-width:260px;max-height:340px;overflow-y:auto;">
        <div class="form-check mb-2 border-bottom pb-2">
          <input class="form-check-input" type="checkbox" id="vacPersonAll" checked>
          <label class="form-check-label fw-semibold" for="vacPersonAll"><?= e(t('vacations.all_people')) ?></label>
        </div>
        <?php foreach ($people as $p): ?>
          <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input vac-person-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" id="vacPerson<?= (int)$p['id'] ?>">
            <span class="af-person-swatch rounded-circle d-inline-block" data-person-id="<?= (int)$p['id'] ?>" style="width:10px;height:10px;"></span>
            <label class="form-check-label" for="vacPerson<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="afVacations.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('vacations.add_vacation')) ?></button>
  </div>
</div>

<div class="af-card mb-3">
  <div id="vacationsCalendarEl"></div>
</div>

<div class="af-card p-0">
  <div class="p-3 border-bottom">
    <h6 class="mb-0"><?= e(t('vacations.conflicts_title')) ?></h6>
    <p class="text-muted small mb-0"><?= e(t('vacations.conflicts_subtitle')) ?></p>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th><?= e(t('vacations.col_person')) ?></th>
        <th><?= e(t('vacations.col_vacation_dates')) ?></th>
        <th><?= e(t('vacations.col_task')) ?></th>
        <th><?= e(t('vacations.col_task_dates')) ?></th>
        <th><?= e(t('vacations.col_project')) ?></th>
        <th></th>
      </tr></thead>
      <tbody id="vacationConflictsBody"></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="vacationModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="vacationForm">
    <div class="modal-header"><h5 class="modal-title" id="vacationModalTitle"><?= e(t('vacations.new_vacation')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="vac_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('vacations.field_person')) ?></label>
        <?php if (is_admin()): ?>
          <select class="form-select" name="person_id" id="vac_person_id" required>
            <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$personId ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
          </select>
        <?php else: ?>
          <input class="form-control" value="<?= e($currentPersonName) ?>" disabled>
          <input type="hidden" name="person_id" id="vac_person_id" value="<?= (int)$personId ?>">
        <?php endif; ?>
      </div>
      <div class="row">
        <div class="col-md-6 mb-2">
          <label class="form-label"><?= e(t('vacations.field_start_date')) ?></label>
          <input type="date" class="form-control" name="start_date" id="vac_start_date" required>
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label"><?= e(t('vacations.field_end_date')) ?></label>
          <input type="date" class="form-control" name="end_date" id="vac_end_date" required>
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label"><?= e(t('vacations.field_notes')) ?></label>
        <input class="form-control" name="notes" id="vac_notes" maxlength="255">
        <div class="form-text"><?= e(t('vacations.notes_hint')) ?></div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline-danger me-auto d-none" id="vac_delete_btn"><i class="bi bi-trash3"></i> <?= e(t('vacations.delete_vacation')) ?></button>
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
      <button type="submit" class="btn btn-primary" id="vac_save_btn"><?= e(t('common.save')) ?></button>
    </div>
  </form>
</div></div></div>

<script>
window.AF_I18N_VACATIONS = {
  allPeople: <?= json_encode(t('vacations.all_people')) ?>,
  personSingular: <?= json_encode(t('vacations.person_singular')) ?>,
  personPlural: <?= json_encode(t('vacations.person_plural')) ?>,
  newTitle: <?= json_encode(t('vacations.new_vacation')) ?>,
  viewMonth: <?= json_encode(t('vacations.view_month')) ?>,
  viewYear: <?= json_encode(t('vacations.view_year')) ?>,
  today: <?= json_encode(t('vacations.today')) ?>,
  noConflicts: <?= json_encode(t('vacations.no_conflicts')) ?>,
  open: <?= json_encode(t('common.open')) ?>,
  deleteConfirm: <?= json_encode(t('vacations.delete_vacation_confirm')) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

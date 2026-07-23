<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$projects = list_projects(['is_archived' => 0]);
$departments = department_list();
$categories = db()->query('SELECT * FROM activity_categories WHERE is_active = 1 ORDER BY name')->fetchAll();

$pageTitle = t('reports.title');
$activeNav = 'reports';
$breadcrumbs = [['label' => t('reports.title')]];
$pageScripts = [base_url('assets/js/reports.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-3"><?= e(t('reports.title')) ?></h4>
<div class="row">
  <div class="col-lg-3 no-print">
    <div class="af-card p-2">
      <div class="list-group list-group-flush" id="reportList">
        <?php foreach (REPORT_DEFINITIONS as $key => $label): ?>
          <button type="button" class="list-group-item list-group-item-action small" data-report="<?= e($key) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-9">
    <div class="af-card mb-3 no-print">
      <form id="reportFilters" class="row g-2">
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.date_from')) ?></label><input type="date" class="form-control form-control-sm" name="date_from"></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.date_to')) ?></label><input type="date" class="form-control form-control-sm" name="date_to"></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.employee')) ?></label><select class="form-select form-select-sm" name="employee_id"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.requester')) ?></label><select class="form-select form-select-sm" name="requester_id"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.project')) ?></label><select class="form-select form-select-sm" name="project_id"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.department')) ?></label><select class="form-select form-select-sm" name="department_id"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.activity_type')) ?></label><select class="form-select form-select-sm" name="activity_type"><option value=""><?= e(t('common.all')) ?></option><option value="planned"><?= e(t('tasks.planned')) ?></option><option value="unplanned"><?= e(t('tasks.unplanned')) ?></option></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.adhoc')) ?></label><select class="form-select form-select-sm" name="is_adhoc"><option value=""><?= e(t('common.all')) ?></option><option value="1"><?= e(t('reports.adhoc_only')) ?></option><option value="0"><?= e(t('reports.non_adhoc')) ?></option></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.status')) ?></label><select class="form-select form-select-sm" name="status"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach (ACTIVITY_STATUSES as $s): ?><option value="<?= $s ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.priority')) ?></label><select class="form-select form-select-sm" name="priority"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach (ACTIVITY_PRIORITIES as $p): ?><option value="<?= $p ?>"><?= e(status_label($p)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.request_channel')) ?></label><select class="form-select form-select-sm" name="request_channel"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach (REQUEST_CHANNELS as $c): ?><option value="<?= $c ?>"><?= e(request_channel_label($c)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><?= e(t('reports.category')) ?></label><select class="form-select form-select-sm" name="category_id"><option value=""><?= e(t('common.all')) ?></option>
          <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><?= e(t('reports.run_report')) ?></button></div>
      </form>
    </div>

    <div class="af-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h6 class="mb-0" id="reportTitle"><?= e(t('reports.select_report')) ?></h6>
          <div class="small text-muted" id="reportMeta"></div>
        </div>
        <div class="no-print">
          <button class="btn btn-sm btn-outline-secondary" id="exportCsv"><i class="bi bi-filetype-csv"></i> <?= e(t('common.export_csv')) ?></button>
          <button class="btn btn-sm btn-outline-secondary" id="printReport"><i class="bi bi-printer"></i> <?= e(t('common.print')) ?></button>
        </div>
      </div>
      <div class="table-responsive"><table class="table table-sm table-hover" id="reportTable"><thead></thead><tbody></tbody></table></div>
      <div class="af-empty" id="reportEmpty"><i class="bi bi-bar-chart-line"></i><?= e(t('reports.choose_hint')) ?></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

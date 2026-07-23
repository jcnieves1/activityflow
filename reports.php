<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$projects = list_projects(['is_archived' => 0]);
$departments = department_list();
$categories = db()->query('SELECT * FROM activity_categories WHERE is_active = 1 ORDER BY name')->fetchAll();

$pageTitle = 'Reports Center';
$activeNav = 'reports';
$breadcrumbs = [['label' => 'Reports Center']];
$pageScripts = [base_url('assets/js/reports.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-3">Reports Center</h4>
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
        <div class="col-md-3"><label class="form-label small mb-0">Date from</label><input type="date" class="form-control form-control-sm" name="date_from"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Date to</label><input type="date" class="form-control form-control-sm" name="date_to"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Employee</label><select class="form-select form-select-sm" name="employee_id"><option value="">All</option>
          <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Requester</label><select class="form-select form-select-sm" name="requester_id"><option value="">All</option>
          <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Project</label><select class="form-select form-select-sm" name="project_id"><option value="">All</option>
          <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Department</label><select class="form-select form-select-sm" name="department_id"><option value="">All</option>
          <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Activity type</label><select class="form-select form-select-sm" name="activity_type"><option value="">All</option><option value="planned">Planned</option><option value="unplanned">Unplanned</option></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Ad-hoc</label><select class="form-select form-select-sm" name="is_adhoc"><option value="">All</option><option value="1">Ad-hoc only</option><option value="0">Non ad-hoc</option></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option>
          <?php foreach (ACTIVITY_STATUSES as $s): ?><option value="<?= $s ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Priority</label><select class="form-select form-select-sm" name="priority"><option value="">All</option>
          <?php foreach (ACTIVITY_PRIORITIES as $p): ?><option value="<?= $p ?>"><?= e(status_label($p)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Request channel</label><select class="form-select form-select-sm" name="request_channel"><option value="">All</option>
          <?php foreach (REQUEST_CHANNELS as $c): ?><option value="<?= $c ?>"><?= e(request_channel_label($c)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0">Category</label><select class="form-select form-select-sm" name="category_id"><option value="">All</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">Run report</button></div>
      </form>
    </div>

    <div class="af-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h6 class="mb-0" id="reportTitle">Select a report</h6>
          <div class="small text-muted" id="reportMeta"></div>
        </div>
        <div class="no-print">
          <button class="btn btn-sm btn-outline-secondary" id="exportCsv"><i class="bi bi-filetype-csv"></i> CSV</button>
          <button class="btn btn-sm btn-outline-secondary" id="printReport"><i class="bi bi-printer"></i> Print / PDF</button>
        </div>
      </div>
      <div class="table-responsive"><table class="table table-sm table-hover" id="reportTable"><thead></thead><tbody></tbody></table></div>
      <div class="af-empty" id="reportEmpty"><i class="bi bi-bar-chart-line"></i>Choose a report from the list to get started.</div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

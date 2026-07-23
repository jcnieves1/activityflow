<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people([
    'search' => $_GET['search'] ?? '',
    'is_active' => $_GET['status'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
]);
$departments = department_list();
$allActivePeople = list_people(['is_active' => 1]);

$pageTitle = 'People Directory';
$activeNav = 'people';
$breadcrumbs = [['label' => 'People Directory']];
$pageScripts = [base_url('assets/js/people.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0">People Directory</h4>
    <p class="text-muted small mb-0">Requesters and employees referenced by tasks. A person doesn't need a login account.</p>
  </div>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#personModal" onclick="afPeople.openCreate()">
    <i class="bi bi-person-plus"></i> Add person
  </button>
</div>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-4">
    <input type="text" class="form-control" name="search" placeholder="Search name, email, title…" value="<?= e($_GET['search'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <select class="form-select" name="department_id">
      <option value="">All departments</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= (int)$d['id'] ?>" <?= (string)($_GET['department_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select class="form-select" name="status">
      <option value="">Active &amp; inactive</option>
      <option value="1" <?= ($_GET['status'] ?? '') === '1' ? 'selected' : '' ?>>Active only</option>
      <option value="0" <?= ($_GET['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive only</option>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</form>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Name</th><th>Title</th><th>Department</th><th>Contact</th><th>Manager</th><th>Status</th><th>System account</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($people as $p): ?>
        <tr>
          <td class="fw-semibold"><?= e($p['full_name']) ?></td>
          <td><?= e($p['job_title'] ?? '—') ?></td>
          <td><?= e($p['department_name'] ?? '—') ?></td>
          <td class="small"><?= e($p['email'] ?? '') ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?></td>
          <td><?= e($p['manager_name'] ?? '—') ?></td>
          <td><span class="badge bg-<?= $p['is_active'] ? 'success' : 'secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td><?= $p['user_id'] ? '<i class="bi bi-check-circle text-success"></i>' : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" onclick='afPeople.openEdit(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
            <?php if (is_admin()): ?>
              <button class="btn btn-sm btn-outline-<?= $p['is_active'] ? 'danger' : 'success' ?>" onclick="afPeople.toggleActive(<?= (int)$p['id'] ?>, <?= $p['is_active'] ? 'false' : 'true' ?>)">
                <?= $p['is_active'] ? 'Deactivate' : 'Reactivate' ?>
              </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$people): ?>
        <tr><td colspan="8"><div class="af-empty"><i class="bi bi-person-lines-fill"></i>No people match these filters.</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit person modal -->
<div class="modal fade" id="personModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="personForm">
        <div class="modal-header">
          <h5 class="modal-title" id="personModalTitle">Add person</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="pf_id">
          <div id="personDuplicateWarning" class="alert alert-warning d-none"></div>
          <div class="mb-2"><label class="form-label">Full name *</label><input class="form-control" name="full_name" id="pf_full_name" required></div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">Job title</label><input class="form-control" name="job_title" id="pf_job_title"></div>
            <div class="col-6 mb-2"><label class="form-label">Department</label>
              <select class="form-select" name="department_id" id="pf_department_id">
                <option value="">—</option>
                <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">Organization</label><input class="form-control" name="organization" id="pf_organization"></div>
            <div class="col-6 mb-2"><label class="form-label">Role</label><input class="form-control" name="org_role" id="pf_org_role" placeholder="e.g. Requester, Employee"></div>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="pf_email"></div>
            <div class="col-6 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" id="pf_phone"></div>
          </div>
          <div class="mb-2"><label class="form-label">Manager</label>
            <select class="form-select" name="manager_id" id="pf_manager_id">
              <option value="">—</option>
              <?php foreach ($allActivePeople as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="pf_notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="personSaveBtn">Save person</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>

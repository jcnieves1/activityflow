<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

$pageTitle = t('admin.departments_title');
$activeNav = 'admin_departments';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.departments_title')]];
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?= e(t('admin.departments_title')) ?></h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="afDept.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_department')) ?></button>
</div>
<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th><?= e(t('admin.col_department_name')) ?></th><th><?= e(t('common.status')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($departments as $d): ?>
      <tr>
        <td class="fw-semibold"><?= e($d['name']) ?></td>
        <td><span class="badge bg-<?= $d['is_active'] ? 'success' : 'secondary' ?>"><?= $d['is_active'] ? e(t('admin.col_active')) : e(t('people.inactive')) ?></span></td>
        <td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick='afDept.openEdit(<?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="deptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="deptForm">
    <div class="modal-header"><h5 class="modal-title" id="deptModalTitle"><?= e(t('admin.new_department')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="dept_id">
      <div class="mb-2"><label class="form-label"><?= e(t('admin.name_required')) ?></label><input class="form-control" name="name" id="dept_name" required></div>
      <div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="dept_is_active" value="1" checked><label class="form-check-label" for="dept_is_active"><?= e(t('admin.col_active')) ?></label></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>
<?php
// Rendered via $inlineScript (see includes/layout_footer.php) so it runs AFTER
// Bootstrap's JS bundle and app.js have loaded — placing this in a plain
// <script> tag here would run before those load and throw "bootstrap is not
// defined" / "afFetch is not defined", which is what broke Add/Edit.
$deptNewLabel = json_encode(t('admin.new_department'));
$deptEditLabel = json_encode(t('admin.edit_department'));
$inlineScript = <<<JS
window.afDept = (function () {
  const modal = new bootstrap.Modal(document.getElementById('deptModal'));
  const form = document.getElementById('deptForm');
  function openCreate() { form.reset(); document.getElementById('dept_id').value = ''; document.getElementById('deptModalTitle').textContent = {$deptNewLabel}; }
  function openEdit(d) {
    document.getElementById('dept_id').value = d.id;
    document.getElementById('dept_name').value = d.name;
    document.getElementById('dept_is_active').checked = !!Number(d.is_active);
    document.getElementById('deptModalTitle').textContent = {$deptEditLabel};
    modal.show();
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_active = form.querySelector('[name=is_active]').checked ? 1 : 0;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'department_save' }, data) })
      .then(() => location.reload()).catch((err) => afToast(err.message, 'danger'));
  });
  return { openCreate, openEdit };
})();
JS;
require __DIR__ . '/../includes/layout_footer.php';
?>

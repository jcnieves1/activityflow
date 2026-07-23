<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$categories = db()->query('SELECT * FROM activity_categories ORDER BY name')->fetchAll();

$pageTitle = t('admin.categories_title');
$activeNav = 'admin_categories';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.categories_title')]];
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?= e(t('admin.categories_title')) ?></h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="afCat.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_category')) ?></button>
</div>
<p class="text-muted small"><?= e(t('admin.priorities_note')) ?></p>
<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th><?= e(t('admin.col_category_name')) ?></th><th><?= e(t('admin.col_description')) ?></th><th><?= e(t('common.status')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td class="fw-semibold"><?= e($c['name']) ?></td>
        <td class="text-muted small"><?= e($c['description'] ?? '') ?></td>
        <td><span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? e(t('admin.col_active')) : e(t('people.inactive')) ?></span></td>
        <td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick='afCat.openEdit(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="catForm">
    <div class="modal-header"><h5 class="modal-title" id="catModalTitle"><?= e(t('admin.new_category')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="cat_id">
      <div class="mb-2"><label class="form-label"><?= e(t('admin.name_required')) ?></label><input class="form-control" name="name" id="cat_name" required></div>
      <div class="mb-2"><label class="form-label"><?= e(t('admin.col_description')) ?></label><textarea class="form-control" name="description" id="cat_description" rows="2"></textarea></div>
      <div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="cat_is_active" value="1" checked><label class="form-check-label" for="cat_is_active"><?= e(t('admin.col_active')) ?></label></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>
<?php
// Rendered via $inlineScript (see includes/layout_footer.php) so it runs AFTER
// Bootstrap's JS bundle and app.js have loaded — placing this in a plain
// <script> tag here would run before those load and throw "bootstrap is not
// defined" / "afFetch is not defined", which is what broke Add/Edit.
$catNewLabel = json_encode(t('admin.new_category'));
$catEditLabel = json_encode(t('admin.edit_category'));
$inlineScript = <<<JS
window.afCat = (function () {
  const modal = new bootstrap.Modal(document.getElementById('catModal'));
  const form = document.getElementById('catForm');
  function openCreate() {
    form.reset(); document.getElementById('cat_id').value = '';
    document.getElementById('catModalTitle').textContent = {$catNewLabel};
  }
  function openEdit(c) {
    document.getElementById('cat_id').value = c.id;
    document.getElementById('cat_name').value = c.name;
    document.getElementById('cat_description').value = c.description || '';
    document.getElementById('cat_is_active').checked = !!Number(c.is_active);
    document.getElementById('catModalTitle').textContent = {$catEditLabel};
    modal.show();
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_active = form.querySelector('[name=is_active]').checked ? 1 : 0;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'category_save' }, data) })
      .then(() => location.reload()).catch((err) => afToast(err.message, 'danger'));
  });
  return { openCreate, openEdit };
})();
JS;
require __DIR__ . '/../includes/layout_footer.php';
?>

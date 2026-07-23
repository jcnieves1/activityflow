<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$roles = db()->query('SELECT * FROM roles ORDER BY name')->fetchAll();

$pageTitle = t('admin.users_title');
$activeNav = 'admin_users';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.users_title')]];
$pageScripts = [base_url('assets/js/admin_users.js')];
require __DIR__ . '/../includes/layout_header.php';
?>
<h4 class="mb-3"><?= e(t('admin.users_title')) ?></h4>
<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="usersTable">
      <thead class="table-light"><tr><th><?= e(t('admin.col_name')) ?></th><th><?= e(t('admin.col_email')) ?></th><th><?= e(t('admin.col_roles')) ?></th><th><?= e(t('admin.col_status')) ?></th><th><?= e(t('admin.col_last_login')) ?></th><th></th></tr></thead>
      <tbody><tr><td colspan="6" class="text-center text-muted p-4"><?= e(t('common.loading')) ?></td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="rolesModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= e(t('admin.assign_roles')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="rolesModalBody">
      <?php foreach ($roles as $r): ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="<?= (int)$r['id'] ?>" data-role-name="<?= e($r['name']) ?>" id="role_<?= (int)$r['id'] ?>">
          <label class="form-check-label" for="role_<?= (int)$r['id'] ?>"><?= e(status_label($r['name'])) ?> — <span class="text-muted small"><?= e($r['description']) ?></span></label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary" id="saveRolesBtn"><?= e(t('admin.save_roles')) ?></button></div>
  </div></div>
</div>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>

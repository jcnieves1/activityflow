<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

// Viewing releases is open to everyone; adding, editing, and deleting them
// (and everything on the detail page — phases, project association) is
// Administrator-only. The write actions are enforced server-side too (every
// release_* action in api/admin.php sits behind require_role([ROLE_ADMIN])),
// so hiding the buttons here is purely so non-admins aren't shown controls
// that would just 403 — it's not the actual security boundary.
$releases = list_releases();
$defaultPhaseNames = array_column(list_release_phase_templates(), 'name');

$pageTitle = t('admin.releases_title');
$activeNav = 'releases';
$breadcrumbs = [['label' => t('nav.releases')]];
$pageScripts = [base_url('assets/js/releases.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('admin.releases_title')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('admin.releases_subtitle')) ?></p>
  </div>
  <?php if (is_admin()): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#releaseModal" onclick="afReleases.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_release')) ?></button>
  <?php endif; ?>
</div>
<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th><?= e(t('admin.col_release_name')) ?></th>
      <th><?= e(t('admin.col_start_date')) ?></th>
      <th><?= e(t('admin.col_launch_date')) ?></th>
      <th><?= e(t('admin.col_phases')) ?></th>
      <th><?= e(t('admin.col_projects')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($releases as $r): ?>
      <tr>
        <td class="fw-semibold"><a href="<?= e(base_url('release_detail.php?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></td>
        <td><?= e(format_date($r['start_date'])) ?></td>
        <td><?= e(format_date($r['end_date'])) ?></td>
        <td><?= (int)$r['phase_count'] ?></td>
        <td><?= (int)$r['project_count'] ?></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('release_detail.php?id=' . (int)$r['id'])) ?>"><i class="bi bi-gear"></i> <?= e(t('admin.manage_release')) ?></a>
          <?php if (is_admin()): ?>
            <button class="btn btn-sm btn-outline-secondary" onclick='afReleases.openEdit(<?= json_encode(['id' => (int)$r['id'], 'name' => $r['name'], 'description' => $r['description'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
            <button class="btn btn-sm btn-outline-danger" onclick='afReleases.openDelete(<?= json_encode(['id' => (int)$r['id'], 'name' => $r['name'], 'project_count' => (int)$r['project_count']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$releases): ?>
      <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('admin.no_releases')) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (is_admin()): ?>
<div class="modal fade" id="releaseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="releaseForm">
    <div class="modal-header"><h5 class="modal-title" id="releaseModalTitle"><?= e(t('admin.new_release')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="release_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('admin.release_name_label')) ?></label>
        <input class="form-control" name="name" id="release_name_input" required maxlength="150">
      </div>
      <div class="mb-2">
        <label class="form-label"><?= e(t('admin.release_description_label')) ?></label>
        <textarea class="form-control" name="description" id="release_description_input" rows="2"></textarea>
      </div>
      <div class="row">
        <div class="col-md-6 mb-2">
          <label class="form-label"><?= e(t('admin.release_start_date_label')) ?></label>
          <input type="date" class="form-control" name="start_date" id="release_start_date_input" required>
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label"><?= e(t('admin.release_launch_date_label')) ?></label>
          <input type="date" class="form-control" name="end_date" id="release_end_date_input" required>
        </div>
      </div>
      <div class="form-text" id="releaseDatesHint">
        <?= $defaultPhaseNames
              ? e(t('admin.release_dates_hint', ['phases' => implode(', ', $defaultPhaseNames)]))
              : e(t('admin.release_dates_hint_none')) ?>
        <a href="<?= e(base_url('admin/release_phase_templates.php')) ?>"><?= e(t('admin.manage_default_phases_link')) ?></a>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="releaseDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('admin.delete_release_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p id="releaseDeleteIntro"></p></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="releaseDeleteConfirmBtn"><i class="bi bi-trash3"></i> <?= e(t('common.delete')) ?></button>
  </div>
</div></div></div>

<script>
window.AF_I18N_RELEASES = {
  newTitle: <?= json_encode(t('admin.new_release')) ?>,
  editTitle: <?= json_encode(t('admin.edit_release')) ?>,
  deleteConfirmSimple: <?= json_encode(t('admin.delete_release_confirm_simple')) ?>,
  deleteConfirmWithProjects: <?= json_encode(t('admin.delete_release_confirm_with_projects')) ?>
};
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

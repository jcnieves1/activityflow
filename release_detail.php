<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$releaseId = (int)($_GET['id'] ?? 0);
$release = get_release($releaseId);
if (!$release) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$phases = list_release_phases($releaseId);
$projectsInRelease = list_projects_in_release($releaseId);
// Everything below that mutates a release (edit/delete, phases, project
// association/move/disassociation) is Administrator-only — see releases.php
// for why. A non-admin never needs these lists, so skip the extra queries.
$unassignedProjects = is_admin() ? list_unassigned_projects() : [];
$otherReleases = is_admin() ? array_values(array_filter(list_releases(), fn($r) => (int)$r['id'] !== $releaseId)) : [];

$pageTitle = $release['name'];
$activeNav = 'releases';
$breadcrumbs = [
    ['label' => t('nav.releases'), 'url' => base_url('releases.php')],
    ['label' => $release['name']],
];
$pageScripts = [base_url('assets/js/release_detail.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <a href="<?= e(base_url('releases.php')) ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(t('admin.back_to_releases')) ?></a>
    <h4 class="mb-0"><?= e($release['name']) ?></h4>
    <div class="text-muted small mt-1">
      <?= e(t('admin.release_start_date_label')) ?>: <?= e(format_date($release['start_date'])) ?>
      &middot; <?= e(t('admin.release_launch_date_label')) ?>: <?= e(format_date($release['end_date'])) ?>
    </div>
    <?php if ($release['description']): ?><p class="text-muted small mt-2 mb-0"><?= e($release['description']) ?></p><?php endif; ?>
  </div>
  <?php if (is_admin()): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#releaseModal" onclick='afReleaseDetail.openEditRelease(<?= json_encode(['id' => (int)$release['id'], 'name' => $release['name'], 'description' => $release['description'], 'start_date' => $release['start_date'], 'end_date' => $release['end_date']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil-square"></i> <?= e(t('admin.edit_release')) ?></button>
    <button class="btn btn-outline-danger" onclick='afReleaseDetail.openDeleteRelease(<?= json_encode(['id' => (int)$release['id'], 'name' => $release['name'], 'project_count' => count($projectsInRelease)], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-trash3"></i> <?= e(t('admin.delete_release_title')) ?></button>
  </div>
  <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 mt-4">
  <h5 class="mb-0"><?= e(t('admin.release_phases_title')) ?></h5>
  <?php if (is_admin()): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#phaseModal" onclick="afReleaseDetail.openCreatePhase()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_phase')) ?></button>
  <?php endif; ?>
</div>
<div class="af-card p-0 mb-4">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th><?= e(t('admin.col_phase_name')) ?></th>
      <th><?= e(t('admin.col_start_date')) ?></th>
      <th><?= e(t('admin.col_end_date')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($phases as $p): ?>
      <tr>
        <td class="fw-semibold"><?= e($p['name']) ?></td>
        <td><?= e(format_date($p['start_date'])) ?></td>
        <td><?= e(format_date($p['end_date'])) ?></td>
        <td class="text-end">
          <?php if (is_admin()): ?>
            <button class="btn btn-sm btn-outline-secondary" onclick='afReleaseDetail.openEditPhase(<?= json_encode(['id' => (int)$p['id'], 'name' => $p['name'], 'start_date' => $p['start_date'], 'end_date' => $p['end_date']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
            <button class="btn btn-sm btn-outline-danger" onclick='afReleaseDetail.openDeletePhase(<?= json_encode(['id' => (int)$p['id'], 'name' => $p['name']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$phases): ?>
      <tr><td colspan="4" class="text-center text-muted py-4"><?= e(t('admin.no_phases')) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0"><?= e(t('admin.release_projects_title')) ?></h5>
</div>
<div class="af-card p-0 mb-3">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th><?= e(t('admin.col_project_name')) ?></th>
      <th><?= e(t('admin.col_project_owner')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($projectsInRelease as $p): ?>
      <tr>
        <td class="fw-semibold"><?= e($p['name']) ?> <span class="text-muted small">(<?= e($p['code']) ?>)</span></td>
        <td><?= e($p['owner_name'] ?? '—') ?></td>
        <td class="text-end">
          <?php if (is_admin()): ?>
            <?php if ($otherReleases): ?>
              <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><?= e(t('admin.move_project')) ?></button>
                <ul class="dropdown-menu">
                  <?php foreach ($otherReleases as $or): ?>
                    <li><a class="dropdown-item" href="#" onclick='afReleaseDetail.moveProject(<?= (int)$p['id'] ?>, <?= (int)$or['id'] ?>); return false;'><?= e($or['name']) ?></a></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-danger" onclick='afReleaseDetail.openDisassociateProject(<?= json_encode(['id' => (int)$p['id'], 'name' => $p['name']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('admin.disassociate_project')) ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$projectsInRelease): ?>
      <tr><td colspan="3" class="text-center text-muted py-4"><?= e(t('admin.no_projects_in_release')) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<script>window.AF_RELEASE_ID = <?= (int)$release['id'] ?>;</script>
<?php if (is_admin()): ?>
<div class="af-card">
  <label class="form-label"><?= e(t('admin.associate_project')) ?></label>
  <?php if ($unassignedProjects): ?>
    <div class="d-flex gap-2">
      <select class="form-select" id="associateProjectSelect">
        <?php foreach ($unassignedProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['code']) ?>)</option><?php endforeach; ?>
      </select>
      <button class="btn btn-primary text-nowrap" onclick="afReleaseDetail.associateProject()"><i class="bi bi-link-45deg"></i> <?= e(t('admin.associate_project')) ?></button>
    </div>
  <?php else: ?>
    <p class="text-muted small mb-0"><?= e(t('admin.no_unassigned_projects')) ?></p>
  <?php endif; ?>
</div>

<div class="modal fade" id="releaseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="releaseForm">
    <div class="modal-header"><h5 class="modal-title" id="releaseModalTitle"><?= e(t('admin.edit_release')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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

<div class="modal fade" id="phaseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="phaseForm">
    <div class="modal-header"><h5 class="modal-title" id="phaseModalTitle"><?= e(t('admin.new_phase')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="phase_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('admin.phase_name_label')) ?></label>
        <input class="form-control" name="name" id="phase_name_input" required maxlength="80">
      </div>
      <div class="row">
        <div class="col-md-6 mb-2">
          <label class="form-label"><?= e(t('admin.phase_start_date_label')) ?></label>
          <input type="date" class="form-control" name="start_date" id="phase_start_date_input" required>
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label"><?= e(t('admin.phase_end_date_label')) ?></label>
          <input type="date" class="form-control" name="end_date" id="phase_end_date_input" required>
        </div>
      </div>
      <div class="form-text"><?= e(t('admin.phase_dates_hint', ['start' => format_date($release['start_date']), 'end' => format_date($release['end_date'])])) ?></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="phaseDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('admin.delete_phase_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p id="phaseDeleteIntro"></p></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="phaseDeleteConfirmBtn"><i class="bi bi-trash3"></i> <?= e(t('common.delete')) ?></button>
  </div>
</div></div></div>

<div class="modal fade" id="disassociateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= e(t('admin.disassociate_project')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p id="disassociateIntro"></p></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="disassociateConfirmBtn"><?= e(t('admin.disassociate_project')) ?></button>
  </div>
</div></div></div>

<script>
window.AF_I18N_RELEASE_DETAIL = {
  editReleaseTitle: <?= json_encode(t('admin.edit_release')) ?>,
  deleteReleaseConfirmSimple: <?= json_encode(t('admin.delete_release_confirm_simple')) ?>,
  deleteReleaseConfirmWithProjects: <?= json_encode(t('admin.delete_release_confirm_with_projects')) ?>,
  newPhaseTitle: <?= json_encode(t('admin.new_phase')) ?>,
  editPhaseTitle: <?= json_encode(t('admin.edit_phase')) ?>,
  deletePhaseConfirm: <?= json_encode(t('admin.delete_phase_confirm')) ?>,
  disassociateConfirm: <?= json_encode(t('admin.disassociate_confirm')) ?>,
  releasesUrl: <?= json_encode(base_url('releases.php')) ?>
};
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

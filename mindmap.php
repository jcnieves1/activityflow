<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

// The Mind Map is a read-only, whole-landscape view open to every role —
// unlike Team Activities' filtered list, there's nothing here a viewer could
// misuse. Per-project visibility (can_view_project()) is still enforced
// inside mindmap_data() itself, so a non-admin/non-viewer never sees a
// project's tasks they couldn't already see elsewhere.
$releases = list_releases();
$projects = array_values(array_filter(list_projects(['is_archived' => 0]), 'can_view_project'));
$people = list_people(['is_active' => 1]);
$statuses = list_task_statuses();

$pageTitle = t('mindmap.title');
$activeNav = 'mindmap';
$breadcrumbs = [['label' => t('mindmap.title')]];
$pageStyles = [
    'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.9/vis-network.min.css',
];
$pageScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.9/standalone/umd/vis-network.min.js',
    base_url('assets/js/activities.js'),
    base_url('assets/js/mindmap.js'),
];
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/activity_modal.php';
?>
<div class="mb-3">
  <h4 class="mb-0"><?= e(t('mindmap.title')) ?></h4>
  <p class="text-muted small mb-0"><?= e(t('mindmap.subtitle')) ?></p>
</div>

<div class="af-card mb-3">
  <form id="mindmapFilterForm" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('mindmap.field_releases')) ?></label>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="mmReleaseBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-rocket-takeoff"></i> <span id="mmReleaseLabel"><?= e(t('mindmap.all_releases')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:240px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="mmReleaseAll" checked>
            <label class="form-check-label fw-semibold" for="mmReleaseAll"><?= e(t('mindmap.all_releases')) ?></label>
          </div>
          <?php if (!$releases): ?><div class="text-muted small"><?= e(t('mindmap.no_releases_yet')) ?></div><?php endif; ?>
          <?php foreach ($releases as $r): ?>
            <div class="form-check">
              <input class="form-check-input mm-release-checkbox" type="checkbox" value="<?= (int)$r['id'] ?>" id="mmRelease<?= (int)$r['id'] ?>">
              <label class="form-check-label" for="mmRelease<?= (int)$r['id'] ?>"><?= e($r['name']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('mindmap.field_projects')) ?></label>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="mmProjectBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-kanban"></i> <span id="mmProjectLabel"><?= e(t('tasks.all_projects')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:240px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="mmProjectAll" checked>
            <label class="form-check-label fw-semibold" for="mmProjectAll"><?= e(t('tasks.all_projects')) ?></label>
          </div>
          <?php foreach ($projects as $p): ?>
            <div class="form-check">
              <input class="form-check-input mm-project-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" id="mmProject<?= (int)$p['id'] ?>">
              <label class="form-check-label" for="mmProject<?= (int)$p['id'] ?>"><?= e($p['name']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('mindmap.field_owners')) ?></label>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="mmOwnerBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-people"></i> <span id="mmOwnerLabel"><?= e(t('board.all_team_members')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:240px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="mmOwnerAll" checked>
            <label class="form-check-label fw-semibold" for="mmOwnerAll"><?= e(t('board.all_team_members')) ?></label>
          </div>
          <?php foreach ($people as $p): ?>
            <div class="form-check">
              <input class="form-check-input mm-owner-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" id="mmOwner<?= (int)$p['id'] ?>">
              <label class="form-check-label" for="mmOwner<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('mindmap.field_statuses')) ?></label>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="mmStatusBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-flag"></i> <span id="mmStatusLabel"><?= e(t('board.all_statuses')) ?></span>
        </button>
        <div class="dropdown-menu p-3" style="min-width:220px;max-height:340px;overflow-y:auto;">
          <div class="form-check mb-2 border-bottom pb-2">
            <input class="form-check-input" type="checkbox" id="mmStatusAll" checked>
            <label class="form-check-label fw-semibold" for="mmStatusAll"><?= e(t('board.all_statuses')) ?></label>
          </div>
          <?php foreach ($statuses as $st): ?>
            <div class="form-check">
              <input class="form-check-input mm-status-checkbox" type="checkbox" value="<?= e($st['slug']) ?>" id="mmStatus<?= e($st['slug']) ?>">
              <label class="form-check-label" for="mmStatus<?= e($st['slug']) ?>"><?= e($st['label']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> <?= e(t('mindmap.run')) ?></button>
    </div>
  </form>
</div>

<div class="af-card mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
      <span><span class="af-mm-swatch" style="background:#6f42c1"></span> <?= e(t('mindmap.legend_release')) ?></span>
      <span><span class="af-mm-swatch af-mm-swatch-gradient"></span> <?= e(t('mindmap.legend_project')) ?></span>
      <span><span class="af-mm-swatch af-mm-swatch-gradient"></span> <?= e(t('mindmap.legend_task')) ?></span>
      <span><span class="af-mm-swatch rounded-circle" style="background:#fd7e14"></span> <?= e(t('mindmap.legend_person')) ?></span>
      <span class="text-muted"><i class="bi bi-info-circle"></i> <?= e(t('mindmap.node_hint')) ?></span>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="mmResetLayout"><i class="bi bi-arrow-repeat"></i> <?= e(t('mindmap.reset_layout')) ?></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="mmDownloadPng"><i class="bi bi-file-earmark-image"></i> <?= e(t('mindmap.download_png')) ?></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="mmDownloadJpg"><i class="bi bi-file-earmark-image"></i> <?= e(t('mindmap.download_jpg')) ?></button>
    </div>
  </div>
  <div id="mindmapCanvas" style="height:70vh;border:1px solid var(--bs-border-color);border-radius:.375rem;background:var(--bs-body-bg);"></div>
  <div class="af-empty d-none" id="mindmapEmpty"><i class="bi bi-diagram-3"></i><?= e(t('mindmap.empty')) ?></div>
</div>

<script>
window.AF_I18N_MINDMAP = {
  noRelease: <?= json_encode(t('mindmap.no_release')) ?>,
  noProject: <?= json_encode(t('mindmap.no_project')) ?>,
  issueTooltip: <?= json_encode(t('tasks.issue_tooltip')) ?>,
  completionLabel: <?= json_encode(t('mindmap.completion_label')) ?>
};
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

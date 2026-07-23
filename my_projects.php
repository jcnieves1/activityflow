<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$personId = current_person_id();
$projects = $personId ? list_projects_for_person($personId) : [];

$pageTitle = t('nav.my_projects');
$activeNav = 'my_projects';
$breadcrumbs = [['label' => t('projects.title'), 'url' => base_url('projects.php')], ['label' => t('nav.my_projects')]];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('nav.my_projects')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('myprojects.subtitle')) ?></p>
  </div>
</div>

<?php if (!$personId): ?>
  <div class="af-empty"><i class="bi bi-person-workspace"></i><?= e(t('myprojects.no_person')) ?></div>
<?php else: ?>
  <div class="row g-3">
  <?php foreach ($projects as $p): $prog = calculate_project_progress((int)$p['id']); ?>
    <div class="col-md-6 col-xl-4">
      <a href="<?= e(base_url('project_detail.php?id=' . $p['id'])) ?>" class="text-decoration-none text-reset">
        <div class="af-card h-100" style="border-top:4px solid <?= e($p['color']) ?>">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold"><?= e($p['name']) ?></div>
              <div class="text-muted small"><?= e($p['code']) ?> · <?= e(t('pd.owner', ['name' => $p['owner_name'] ?? '—'])) ?></div>
            </div>
            <span class="badge <?= status_badge_class($p['status']) ?>"><?= e(status_label($p['status'])) ?></span>
          </div>
          <div class="progress my-2" style="height:8px;">
            <div class="progress-bar" style="width:<?= (float)$prog['percent'] ?>%; background:<?= e($p['color']) ?>"></div>
          </div>
          <div class="d-flex justify-content-between align-items-center small text-muted">
            <span><?= e(t('projects.complete', ['percent' => (float)$prog['percent']])) ?></span>
            <span class="badge bg-light text-dark border"><?= e(status_label($p['project_role'])) ?></span>
          </div>
          <?php if ($p['target_completion_date']): ?>
            <div class="small text-muted mt-1"><i class="bi bi-flag"></i> <?= e(t('projects.target', ['date' => format_date($p['target_completion_date'])])) ?></div>
          <?php endif; ?>
          <?php if (!empty($p['is_archived'])): ?>
            <div class="small text-muted mt-1"><i class="bi bi-archive"></i> <?= e(t('myprojects.archived')) ?></div>
          <?php endif; ?>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
  <?php if (!$projects): ?>
    <div class="col-12"><div class="af-empty"><i class="bi bi-person-workspace"></i><?= e(t('myprojects.empty')) ?></div></div>
  <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>

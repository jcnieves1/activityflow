<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$people = list_people(['is_active' => 1]);
$projects = filter_visible_projects(list_projects(['is_archived' => 0]));

$pageTitle = t('timeline.title');
$activeNav = 'timeline';
$breadcrumbs = [['label' => t('nav.timeline')]];
$pageScripts = [base_url('assets/js/timeline.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-3"><?= e(t('timeline.title')) ?></h4>
<p class="text-muted small"><?= e(t('timeline.subtitle')) ?></p>

<div class="af-card mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-0"><?= e(t('timeline.employee')) ?></label>
      <select class="form-select form-select-sm" id="tlEmployee">
        <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $p['id'] == current_person_id() ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('timeline.date')) ?></label>
      <input type="date" class="form-control form-control-sm" id="tlDate" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('timeline.project')) ?></label>
      <select class="form-select form-select-sm" id="tlProject"><option value=""><?= e(t('timeline.all')) ?></option>
        <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-0"><?= e(t('timeline.requester')) ?></label>
      <select class="form-select form-select-sm" id="tlRequester"><option value=""><?= e(t('timeline.all')) ?></option>
        <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-3">
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="tlShowPlanned" checked><label class="form-check-label small" for="tlShowPlanned"><?= e(t('timeline.planned')) ?></label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="tlShowUnplanned" checked><label class="form-check-label small" for="tlShowUnplanned"><?= e(t('timeline.unplanned')) ?></label>
      </div>
    </div>
  </div>
  <hr>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <button class="btn btn-sm btn-success" id="tlPlay"><i class="bi bi-play-fill"></i> <?= e(t('timeline.play')) ?></button>
    <button class="btn btn-sm btn-outline-secondary" id="tlPause"><i class="bi bi-pause-fill"></i> <?= e(t('timeline.pause')) ?></button>
    <button class="btn btn-sm btn-outline-secondary" id="tlRestart"><i class="bi bi-arrow-counterclockwise"></i> <?= e(t('timeline.restart')) ?></button>
    <select class="form-select form-select-sm" id="tlSpeed" style="width:auto">
      <option value="1">1x</option><option value="2">2x</option><option value="4" selected>4x</option><option value="8">8x</option><option value="20">20x</option>
    </select>
    <div class="flex-fill">
      <input type="range" class="form-range" id="tlScrubber" min="0" max="1440" value="0">
    </div>
    <span class="small text-muted" id="tlClock">06:00</span>
  </div>
</div>

<div class="row">
  <div class="col-lg-9">
    <div id="tlRuler" class="position-relative mb-1" style="height:20px;"></div>
    <div class="af-timeline-track" id="tlPlannedTrack"><span class="label"><?= e(t('timeline.track_planned')) ?></span></div>
    <div class="af-timeline-track" id="tlActualTrack"><span class="label"><?= e(t('timeline.track_actual')) ?></span></div>
    <div class="af-timeline-track" id="tlUnplannedTrack"><span class="label"><?= e(t('timeline.track_unplanned')) ?></span></div>
    <div id="tlMoved" class="small text-muted mt-2"></div>
  </div>
  <div class="col-lg-3">
    <div class="af-card" id="tlDetailPanel">
      <h6><?= e(t('timeline.details')) ?></h6>
      <p class="text-muted small mb-0"><?= e(t('timeline.details_hint')) ?></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

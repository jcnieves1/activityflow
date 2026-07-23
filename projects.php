<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$projects = list_projects([
    'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '', 'is_archived' => 0,
]);
$people = list_people(['is_active' => 1]);
$canCreate = is_admin() || user_has_role(ROLE_PM);
$statuses = ['draft','not_started','active','on_hold','completed','cancelled','archived'];

$pageTitle = 'Projects';
$activeNav = 'projects';
$breadcrumbs = [['label' => 'Projects']];
$pageStyles = ['https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css'];
$pageScripts = ['https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js', base_url('assets/js/projects.js')];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0">Projects</h4>
  <?php if ($canCreate): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal"><i class="bi bi-plus-lg"></i> New project</button>
  <?php endif; ?>
</div>

<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-5"><input type="text" class="form-control" name="search" placeholder="Search name or code…" value="<?= e($_GET['search'] ?? '') ?>"></div>
  <div class="col-md-4">
    <select class="form-select" name="status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</form>

<div class="row g-3">
<?php foreach ($projects as $p): $prog = calculate_project_progress((int)$p['id']); ?>
  <div class="col-md-6 col-xl-4">
    <a href="<?= e(base_url('project_detail.php?id=' . $p['id'])) ?>" class="text-decoration-none text-reset">
      <div class="af-card h-100" style="border-top:4px solid <?= e($p['color']) ?>">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold"><?= e($p['name']) ?></div>
            <div class="text-muted small"><?= e($p['code']) ?> · <?= e($p['owner_name'] ?? '—') ?></div>
          </div>
          <span class="badge <?= status_badge_class($p['status']) ?>"><?= e(status_label($p['status'])) ?></span>
        </div>
        <div class="progress my-2" style="height:8px;">
          <div class="progress-bar" style="width:<?= (float)$prog['percent'] ?>%; background:<?= e($p['color']) ?>"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted">
          <span><?= (float)$prog['percent'] ?>% complete</span>
          <span><?= (int)$p['member_count'] ?> member<?= (int)$p['member_count'] === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($p['target_completion_date']): ?>
          <div class="small text-muted mt-1"><i class="bi bi-flag"></i> Target: <?= e(format_date($p['target_completion_date'])) ?></div>
        <?php endif; ?>
      </div>
    </a>
  </div>
<?php endforeach; ?>
<?php if (!$projects): ?>
  <div class="col-12"><div class="af-empty"><i class="bi bi-kanban"></i>No projects match these filters.</div></div>
<?php endif; ?>
</div>

<?php if ($canCreate): ?>
<div class="modal fade" id="projectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="projectForm">
        <div class="modal-header"><h5 class="modal-title">New project</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-8 mb-2"><label class="form-label">Project name *</label><input class="form-control" name="name" required></div>
            <div class="col-md-4 mb-2"><label class="form-label">Code *</label><input class="form-control" name="code" required placeholder="e.g. PLAT-01"></div>
          </div>
          <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" id="newProjectDescription" rows="2"></textarea></div>
          <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label">Owner / Project Manager *</label>
              <select class="form-select" name="owner_id" required>
                <option value="">Select…</option>
                <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-2"><label class="form-label">Priority</label>
              <select class="form-select" name="priority">
                <option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Target completion</label><input type="date" class="form-control" name="target_completion_date"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Planned effort (hours)</label><input type="number" step="0.5" class="form-control" name="planned_effort_hours"></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label">Status</label>
              <select class="form-select" name="status">
                <?php foreach ($statuses as $s): ?><option value="<?= $s ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-2"><label class="form-label">Calendar color</label><input type="color" class="form-control form-control-color" name="color" value="#4361ee"></div>
          </div>
          <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create project</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

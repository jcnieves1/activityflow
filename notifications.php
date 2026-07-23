<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$notifications = list_notifications($user['id'], 100);

$pageTitle = 'Notifications';
$activeNav = '';
$breadcrumbs = [['label' => 'Notifications']];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Notifications</h4>
  <button class="btn btn-outline-secondary btn-sm" id="markAllRead">Mark all as read</button>
</div>
<div class="af-card p-0">
  <?php foreach ($notifications as $n): ?>
    <div class="p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light' ?>">
      <div class="d-flex justify-content-between">
        <strong><?= e($n['title']) ?></strong>
        <span class="small text-muted"><?= e(format_datetime($n['created_at'])) ?></span>
      </div>
      <?php if ($n['body']): ?><div class="small text-muted"><?= e($n['body']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$notifications): ?><div class="af-empty"><i class="bi bi-bell"></i>No notifications yet.</div><?php endif; ?>
</div>
<script>
document.getElementById('markAllRead').addEventListener('click', function () {
  afFetch(window.AF_BASE_URL + 'api/notifications.php', { method: 'POST', body: { action: 'mark_all_read' } })
    .then(() => location.reload());
});
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>

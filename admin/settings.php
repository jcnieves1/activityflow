<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$config = app_config();
$stats = [
    'Users' => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'People' => (int)db()->query('SELECT COUNT(*) FROM people')->fetchColumn(),
    'Projects' => (int)db()->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'Activities' => (int)db()->query('SELECT COUNT(*) FROM activities')->fetchColumn(),
    'Audit log entries' => (int)db()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
];

$pageTitle = 'System Settings';
$activeNav = 'admin_settings';
$breadcrumbs = [['label' => 'Administration'], ['label' => 'System Settings']];
require __DIR__ . '/../includes/layout_header.php';
?>
<h4 class="mb-3">System Settings</h4>

<div class="row g-3 mb-3">
  <?php foreach ($stats as $label => $val): ?>
    <div class="col-md-2 col-6"><div class="af-card text-center"><div class="af-stat"><?= $val ?></div><div class="text-muted small"><?= e($label) ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="af-card">
      <h6>Environment</h6>
      <table class="table table-sm mb-0">
        <tr><th>PHP version</th><td><?= e(PHP_VERSION) ?></td></tr>
        <tr><th>App environment</th><td><?= e($config['app']['env']) ?></td></tr>
        <tr><th>Base URL</th><td><?= e($config['app']['base_url']) ?></td></tr>
        <tr><th>Session lifetime</th><td><?= (int)$config['app']['session_lifetime_minutes'] ?> minutes</td></tr>
        <tr><th>Timezone</th><td><?= e($config['app']['timezone']) ?></td></tr>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card">
      <h6>Security policy</h6>
      <table class="table table-sm mb-0">
        <tr><th>Login lockout threshold</th><td><?= (int)$config['security']['login_max_attempts'] ?> attempts / <?= (int)$config['security']['login_lockout_minutes'] ?> min</td></tr>
        <tr><th>Recovery lockout threshold</th><td><?= (int)$config['security']['recovery_max_attempts'] ?> attempts / <?= (int)$config['security']['recovery_lockout_minutes'] ?> min</td></tr>
        <tr><th>Recovery token TTL</th><td><?= (int)$config['security']['recovery_token_ttl_minutes'] ?> minutes</td></tr>
        <tr><th>Minimum recovery answer length</th><td><?= (int)$config['security']['min_secret_answer_length'] ?> characters</td></tr>
      </table>
    </div>
  </div>
</div>
<p class="text-muted small mt-3">Database credentials and these values are configured in <code>config/config.php</code> (or environment variables), never hard-coded in application pages.</p>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>

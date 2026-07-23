<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$config = app_config();
$stats = [
    t('admin.stat_users') => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    t('admin.stat_people') => (int)db()->query('SELECT COUNT(*) FROM people')->fetchColumn(),
    t('admin.stat_projects') => (int)db()->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    t('admin.stat_activities') => (int)db()->query('SELECT COUNT(*) FROM activities')->fetchColumn(),
    t('admin.stat_audit_entries') => (int)db()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
];

$pageTitle = t('admin.settings_title');
$activeNav = 'admin_settings';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.settings_title')]];
require __DIR__ . '/../includes/layout_header.php';
?>
<h4 class="mb-3"><?= e(t('admin.settings_title')) ?></h4>

<div class="row g-3 mb-3">
  <?php foreach ($stats as $label => $val): ?>
    <div class="col-md-2 col-6"><div class="af-card text-center"><div class="af-stat"><?= $val ?></div><div class="text-muted small"><?= e($label) ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="af-card">
      <h6><?= e(t('admin.environment')) ?></h6>
      <table class="table table-sm mb-0">
        <tr><th><?= e(t('admin.php_version')) ?></th><td><?= e(PHP_VERSION) ?></td></tr>
        <tr><th><?= e(t('admin.app_environment')) ?></th><td><?= e($config['app']['env']) ?></td></tr>
        <tr><th><?= e(t('admin.base_url')) ?></th><td><?= e($config['app']['base_url']) ?></td></tr>
        <tr><th><?= e(t('admin.session_lifetime')) ?></th><td><?= e(t('admin.minutes', ['n' => (int)$config['app']['session_lifetime_minutes']])) ?></td></tr>
        <tr><th><?= e(t('admin.timezone')) ?></th><td><?= e($config['app']['timezone']) ?></td></tr>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="af-card">
      <h6><?= e(t('admin.security_policy')) ?></h6>
      <table class="table table-sm mb-0">
        <tr><th><?= e(t('admin.login_lockout')) ?></th><td><?= e(t('admin.attempts_per_minutes', ['attempts' => (int)$config['security']['login_max_attempts'], 'minutes' => (int)$config['security']['login_lockout_minutes']])) ?></td></tr>
        <tr><th><?= e(t('admin.recovery_lockout')) ?></th><td><?= e(t('admin.attempts_per_minutes', ['attempts' => (int)$config['security']['recovery_max_attempts'], 'minutes' => (int)$config['security']['recovery_lockout_minutes']])) ?></td></tr>
        <tr><th><?= e(t('admin.recovery_ttl')) ?></th><td><?= e(t('admin.minutes', ['n' => (int)$config['security']['recovery_token_ttl_minutes']])) ?></td></tr>
        <tr><th><?= e(t('admin.min_recovery_length')) ?></th><td><?= e(t('admin.characters', ['n' => (int)$config['security']['min_secret_answer_length']])) ?></td></tr>
      </table>
    </div>
  </div>
</div>
<p class="text-muted small mt-3"><?= e(t('admin.config_note_prefix')) ?> <code>config/config.php</code> <?= e(t('admin.config_note_suffix')) ?></p>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>

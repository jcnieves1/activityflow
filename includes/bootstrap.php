<?php
declare(strict_types=1);

/**
 * Included at the top of every page and API endpoint. Configures sessions
 * securely, loads the config/db/helper layer, and starts the session.
 */

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('ActivityFlow is not configured yet. Copy config/config.sample.php to config/config.php and set your database credentials.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models/audit.php';
require_once __DIR__ . '/models/notifications.php';
require_once __DIR__ . '/models/people.php';
require_once __DIR__ . '/models/vacations.php';
require_once __DIR__ . '/models/projects.php';
require_once __DIR__ . '/models/release_phase_templates.php';
require_once __DIR__ . '/models/releases.php';
require_once __DIR__ . '/models/task_statuses.php';
require_once __DIR__ . '/models/request_channels.php';
require_once __DIR__ . '/models/activities.php';
require_once __DIR__ . '/models/workload.php';
require_once __DIR__ . '/models/mindmap.php';
require_once __DIR__ . '/models/task_templates.php';
require_once __DIR__ . '/models/presence.php';
require_once __DIR__ . '/models/time_entries.php';
require_once __DIR__ . '/models/dashboard.php';
require_once __DIR__ . '/models/reports.php';

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

$isProduction = ($config['app']['env'] ?? 'development') === 'production';
// API endpoints always return JSON, so a displayed PHP warning/notice would get
// printed into the response body and corrupt it client-side (the browser then
// fails to parse the JSON at all). Never display errors inline for /api/
// requests — always log them instead — regardless of environment.
$isApiRequest = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
ini_set('display_errors', ($isProduction || $isApiRequest) ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';

    session_name($config['app']['session_name'] ?? 'activityflow_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle session timeout.
    $lifetimeSeconds = (int)($config['app']['session_lifetime_minutes'] ?? 120) * 60;
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetimeSeconds) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Your session expired. Please log in again.'];
    }
    $_SESSION['last_activity'] = time();
}

// Presence heartbeat: bumps the logged-in user's last_seen_at on every
// request (throttled inside touch_user_presence() itself, so this is cheap
// to call unconditionally here rather than threading it through every page).
// See includes/models/presence.php for the "Online (x)" topbar widget this feeds.
if (!empty($_SESSION['user']['id'])) {
    touch_user_presence((int)$_SESSION['user']['id']);
}

<?php
/**
 * Copy this file to config.php (same folder) and fill in real values.
 * config.php is git-ignored / protected by .htaccess and must never be
 * committed or exposed publicly. Values can also be supplied via
 * environment variables of the same name (useful on shared hosting),
 * which take precedence over the defaults below.
 */

$env = static fn(string $key, $default = null) => getenv($key) !== false ? getenv($key) : $default;

return [
    'db' => [
        'host'    => $env('AF_DB_HOST', '127.0.0.1'),
        'port'    => $env('AF_DB_PORT', '3306'),
        'name'    => $env('AF_DB_NAME', 'juanca44_activityflow'),
        'user'    => $env('AF_DB_USER', 'juanca44_activityflow_user'),
        'pass'    => $env('AF_DB_PASS', 'Michael1Scott'),
        'charset' => 'utf8mb4',
    ],
    'app' => [
        // No trailing slash. e.g. http://localhost/activityflow
        'base_url'                 => $env('AF_BASE_URL', 'https://activityflow.jcnieves.com'),
        'session_name'             => 'activityflow_session',
        'session_lifetime_minutes' => 7200,
        'timezone'                 => $env('AF_TIMEZONE', 'UTC'),
        'env'                      => $env('AF_ENV', 'development'), // development | production
    ],
    'security' => [
        'login_max_attempts'        => 5,
        'login_lockout_minutes'     => 15,
        'recovery_max_attempts'     => 5,
        'recovery_lockout_minutes'  => 30,
        'min_secret_answer_length'  => 3,
        'recovery_token_ttl_minutes'=> 10,
    ],
];


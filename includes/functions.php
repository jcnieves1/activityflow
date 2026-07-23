<?php
declare(strict_types=1);

/** Escape for safe HTML output. Use on every piece of user-supplied data echoed into a page. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    return $config;
}

function base_url(string $path = ''): string
{
    $base = rtrim(app_config()['app']['base_url'], '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    $url = preg_match('#^https?://#i', $path) ? $path : base_url($path);
    header('Location: ' . $url);
    exit;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}

function is_json_request(): bool
{
    return ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** Reads JSON body or falls back to $_POST, for API endpoints that accept either. */
function request_input(): array
{
    if (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_all(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Normalizes a datetime-local input value ("YYYY-MM-DDTHH:MM") to MySQL DATETIME format. Passes through anything else unchanged. */
function normalize_dt(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $value = str_replace('T', ' ', trim($value));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    return $value;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Normalizes a secret answer before hashing/comparison: trims, lowercases, collapses whitespace. */
function normalize_secret_answer(string $answer): string
{
    $answer = mb_strtolower(trim($answer));
    return preg_replace('/\s+/', ' ', $answer) ?? $answer;
}

function format_minutes(?int $minutes): string
{
    if ($minutes === null) {
        return '—';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0 && $m > 0) {
        return "{$h}h {$m}m";
    }
    if ($h > 0) {
        return "{$h}h";
    }
    return "{$m}m";
}

function format_datetime(?string $dt, string $format = 'M j, Y g:i A'): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date($format, $ts) : '—';
}

function format_date(?string $d, string $format = 'M j, Y'): string
{
    return format_datetime($d, $format);
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'backlog'     => 'bg-secondary',
        'planned'     => 'bg-primary',
        'ready'       => 'bg-info text-dark',
        'in_progress' => 'bg-primary',
        'blocked'     => 'bg-dark',
        'waiting'     => 'bg-warning text-dark',
        'completed'   => 'bg-success',
        'cancelled'   => 'bg-secondary',
        default       => 'bg-secondary',
    };
}

function priority_badge_class(string $priority): string
{
    return match ($priority) {
        'urgent' => 'bg-danger',
        'high'   => 'bg-warning text-dark',
        'normal' => 'bg-info text-dark',
        'low'    => 'bg-light text-dark border',
        default  => 'bg-light text-dark border',
    };
}

function activity_type_badge(string $type): string
{
    return $type === 'unplanned'
        ? '<span class="badge bg-orange"><i class="bi bi-lightning-fill"></i> Unplanned</span>'
        : '<span class="badge bg-primary"><i class="bi bi-calendar-check"></i> Planned</span>';
}

function request_channel_label(?string $channel): string
{
    if (!$channel) {
        return '—';
    }
    return ucwords(str_replace('_', ' ', $channel));
}

/**
 * Returns $data[$key] if present and non-empty (not null, '', or missing entirely),
 * otherwise null. Use this instead of `$data['key'] ?: null` when $key may be
 * entirely absent from the array (e.g. a field not present in a given form) —
 * accessing a missing array key directly (as `?:` does) raises a PHP warning,
 * which in development mode gets printed into the response body and can break
 * JSON parsing on the client ("Cannot read properties of null" style errors
 * after an otherwise-successful save).
 */
function nz(array $data, string $key)
{
    return empty($data[$key]) ? null : $data[$key];
}

/** Validate an array of required keys are present and non-empty in $data. Returns list of missing keys. */
function missing_fields(array $data, array $required): array
{
    $missing = [];
    foreach ($required as $field) {
        if (!array_key_exists($field, $data) || trim((string)$data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

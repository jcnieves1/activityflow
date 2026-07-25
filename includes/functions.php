<?php
declare(strict_types=1);

/** Escape for safe HTML output. Use on every piece of user-supplied data echoed into a page. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize a fragment of rich-text HTML (e.g. from the project description
 * WYSIWYG editor) down to a small allow-list of formatting tags/attributes.
 * Call this on write, before storing rich text in the database — output code
 * can then echo the stored value directly without e(), because it is
 * guaranteed to already be safe.
 *
 * - Tags not on the allow-list are unwrapped (their content is kept, the tag
 *   itself is dropped); <script>/<style> are removed entirely, contents included.
 * - All attributes are stripped except href/target/rel on <a>, and href is only
 *   kept if it uses http(s) or mailto (blocks javascript: and similar).
 */
function sanitize_html(?string $html): string
{
    $html = trim((string)$html);
    if ($html === '') {
        return '';
    }

    $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'ol', 'ul', 'li', 'blockquote', 'h1', 'h2', 'h3', 'a'];
    $removedEntirely = ['script', 'style'];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $ok = $dom->loadHTML(
        '<?xml encoding="UTF-8"?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    if (!$ok || !$dom->documentElement) {
        // Parsing failed outright (shouldn't normally happen) — never fall through
        // to echoing this as raw HTML; treat it as plain text instead.
        return e($html);
    }

    _sanitize_html_children($dom->documentElement, $allowedTags, $removedEntirely);

    $out = '';
    foreach (iterator_to_array($dom->documentElement->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

function _sanitize_html_children(DOMNode $node, array $allowedTags, array $removedEntirely): void
{
    $child = $node->firstChild;
    while ($child !== null) {
        $next = $child->nextSibling;

        if ($child instanceof DOMComment) {
            $node->removeChild($child);
            $child = $next;
            continue;
        }

        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);

            if (in_array($tag, $removedEntirely, true)) {
                $node->removeChild($child);
                $child = $next;
                continue;
            }

            // Recurse first so nested disallowed tags are cleaned regardless of
            // what happens to this element itself.
            _sanitize_html_children($child, $allowedTags, $removedEntirely);

            if (!in_array($tag, $allowedTags, true)) {
                // Unwrap: replace this element with its (already-cleaned) children.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                $child = $next;
                continue;
            }

            $safeHref = null;
            if ($tag === 'a') {
                $rawHref = trim($child->getAttribute('href'));
                if ($rawHref !== '' && preg_match('#^(https?://|mailto:)#i', $rawHref)) {
                    $safeHref = $rawHref;
                }
            }

            if ($child->hasAttributes()) {
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $child->removeAttribute($attr->name);
                }
            }

            if ($tag === 'a') {
                if ($safeHref !== null) {
                    $child->setAttribute('href', $safeHref);
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener noreferrer nofollow');
                } else {
                    // No safe href: unwrap the link but keep its text.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
        }

        $child = $next;
    }
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

/**
 * Sends a JSON payload. The transport-level HTTP status is always 200 — the
 * payload's own `ok` boolean (and `error` string, when `ok` is false) carries
 * the real success/failure result; the semantic status passed by the caller
 * is still included in the body as `status` for reference/debugging.
 *
 * This is intentional, not an oversight: 401/403/404/419/500 are also the
 * codes .htaccess maps to custom error pages (ErrorDocument). Apache (and
 * some proxy/CDN setups) can intercept a script's own response when it sets
 * one of those status codes and substitute the configured error page's HTML
 * for our JSON body — which silently breaks JSON.parse() on the client even
 * though the request "succeeded" from the application's point of view. Using
 * 200 for every API response sidesteps that class of bug entirely.
 */
function json_response($data, int $status = 200): void
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    if ($status !== 200 && is_array($data) && !array_key_exists('status', $data)) {
        $data['status'] = $status;
    }
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

// request_channel_label() now lives in includes/models/request_channels.php
// — it looks up the admin-editable label from the request_channels table
// (see that file's docblock), rather than a fixed ucwords() transform.

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

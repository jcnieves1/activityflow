<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** Call at the top of any state-changing request handler (POST pages and API endpoints). */
function csrf_require(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token && is_json_request()) {
        $body = request_input();
        $token = $body['csrf_token'] ?? null;
    }
    if (!csrf_verify($token)) {
        if (is_json_request()) {
            json_error('Invalid or expired security token. Please refresh and try again.', 419);
        }
        flash_set('danger', 'Your session security token expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

/**
 * Deliberately NOT gated by require_role([ROLE_ADMIN]) at the top the way
 * api/admin.php is — once an admin is impersonating a non-admin user,
 * $_SESSION['user'] IS that non-admin user for the rest of the request
 * lifecycle, so a blanket admin-only gate here would lock the 'stop' action
 * out from under them the moment impersonation actually starts. 'start'
 * checks is_admin() explicitly; 'stop' only requires that an impersonation
 * is genuinely active in this session (is_impersonating()), which by
 * definition only an admin who started one could be in a position to call.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}
csrf_require();
$data = request_input();
$action = $data['action'] ?? '';

if ($action === 'start') {
    if (!is_admin()) {
        deny(t('impersonation.error_admin_only'));
    }
    if (is_impersonating()) {
        json_error(t('impersonation.error_already_impersonating'));
    }

    $admin = current_user();
    $targetId = (int)($data['user_id'] ?? 0);
    if ($targetId === (int)$admin['id']) {
        json_error(t('impersonation.error_self'));
    }

    $stmt = db()->prepare('SELECT id, full_name, email, status FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        json_error(t('impersonation.error_not_found'), 404);
    }
    if ($target['status'] !== 'active') {
        json_error(t('impersonation.error_inactive'));
    }

    // Admins may impersonate regular accounts to help/troubleshoot, but not
    // each other — keeps "who really did this" unambiguous for the most
    // privileged accounts, and prevents one admin quietly acting under
    // another admin's identity. This mirrors this codebase's existing stance
    // on comment edits (can_edit_comment() in permissions.php), which are
    // restricted even for admins for the same "keep the trail trustworthy"
    // reason.
    $roleStmt = db()->prepare(
        'SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name = ?'
    );
    $roleStmt->execute([$targetId, ROLE_ADMIN]);
    if ($roleStmt->fetchColumn()) {
        json_error(t('impersonation.error_target_admin'));
    }

    // Logged while the admin is still the session's acting user, so the
    // audit trail correctly attributes "who started this" to them rather
    // than to the account they're about to become.
    audit_log('user', $targetId, 'impersonation_started', null, [
        'admin_user_id' => $admin['id'],
        'admin_name'    => $admin['full_name'],
        'target_name'   => $target['full_name'],
    ]);

    $_SESSION['impersonator'] = [
        'id'        => (int)$admin['id'],
        'full_name' => $admin['full_name'],
        'email'     => $admin['email'],
    ];
    load_user_session($targetId);

    json_response(['ok' => true]);
}

if ($action === 'stop') {
    $imp = impersonator_info();
    if (!$imp) {
        json_error(t('impersonation.error_not_impersonating'));
    }

    $impersonatedUser = current_user();
    $impersonatedId = $impersonatedUser['id'] ?? null;

    unset($_SESSION['impersonator']);
    load_user_session((int)$imp['id']);

    if (!current_user()) {
        // The original admin's account no longer loads (e.g. deleted or
        // deactivated while the impersonation was active) — don't leave the
        // session in a broken half-restored state, just end it outright.
        logout_user();
        json_response(['ok' => true, 'logged_out' => true]);
    }

    // Logged only after switching back, so the actor is correctly recorded
    // as the admin who ended it, not the account being stepped away from.
    audit_log('user', $impersonatedId, 'impersonation_ended', null, [
        'admin_user_id' => $imp['id'],
        'admin_name'    => $imp['full_name'],
    ]);

    json_response(['ok' => true]);
}

json_error('Unknown action.', 404);

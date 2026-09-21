<?php
declare(strict_types=1);

/**
 * Self-registered accounts start locked out (users.status = 'pending_approval')
 * until an administrator approves them — see register_user() in
 * includes/auth.php and the status check at the top of attempt_login(). This
 * file owns the approval queue and account_approval_actions, an immutable
 * log of every approve/reject decision ever made (rows are never updated or
 * deleted, so a later re-approval of a previously-rejected account adds a
 * new row rather than erasing the rejection it's reversing). That log both
 * drives the "Decision history" list on admin/account_approvals.php and lets
 * a rejected applicant's next registration or login attempt be shown the
 * actual reason, via latest_account_decision(), instead of a generic error.
 */

/** Accounts currently waiting on a decision, oldest first (first-come, first-served queue). */
function list_pending_accounts(): array
{
    $stmt = db()->query(
        "SELECT id, full_name, email, created_at FROM users
         WHERE status = 'pending_approval' ORDER BY created_at ASC"
    );
    return $stmt->fetchAll();
}

/** Powers the small pending-count badge on the "Account Approvals" nav link. */
function count_pending_accounts(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending_approval'")->fetchColumn();
}

/**
 * Every approve/reject decision ever made, newest first, joined with the
 * target account's current status/name/email and the deciding administrator's
 * name. user_current_status is what drives whether a "Re-approve" action is
 * offered for that row — only when the account is STILL 'rejected' right now
 * (an account can have several rows over time, e.g. rejected then later
 * approved; only the most recent state matters for what action to offer).
 */
function list_account_approval_history(int $limit = 200): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.full_name AS user_full_name, u.email AS user_email, u.status AS user_current_status,
                actor.full_name AS actor_full_name
         FROM account_approval_actions a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN users actor ON actor.id = a.actor_user_id
         ORDER BY a.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * The most recent decision made on a given account, if any — used to surface
 * a rejection reason to the applicant themselves (on a repeat registration
 * attempt, or a login attempt) without exposing the whole history table.
 */
function latest_account_decision(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM account_approval_actions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Approves a pending OR previously-rejected account: flips users.status to
 * 'active' and records the decision. $reason is optional (most first-time
 * approvals need no explanation) but especially useful when re-approving an
 * account that was previously rejected, for later audit — see the "Approve"
 * action offered from the Decision history table for any account whose
 * current status is 'rejected'. Throws InvalidArgumentException if the
 * account doesn't exist or isn't in a state that makes sense to approve from
 * (already active, or deactivated/locked through the unrelated Users & Roles
 * status toggle — those are a separate concern from this approval queue).
 */
function approve_account(int $userId, ?string $reason, int $actorUserId): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new InvalidArgumentException('Account not found.');
    }
    if (!in_array($user['status'], ['pending_approval', 'rejected'], true)) {
        throw new InvalidArgumentException('This account is not awaiting approval.');
    }

    $reason = $reason !== null ? trim($reason) : '';
    $previousStatus = $user['status'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET status = 'active', failed_login_count = 0 WHERE id = ?")->execute([$userId]);
        $pdo->prepare(
            'INSERT INTO account_approval_actions (user_id, action, reason, actor_user_id) VALUES (?, "approved", ?, ?)'
        )->execute([$userId, $reason !== '' ? $reason : null, $actorUserId]);
        audit_log('user', $userId, 'account_approved', ['status' => $previousStatus], ['status' => 'active', 'reason' => $reason !== '' ? $reason : null]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $user['status'] = 'active';
    $user['failed_login_count'] = 0;
    return $user;
}

/**
 * Rejects a pending account: flips users.status to 'rejected' and records
 * the decision. $reason is REQUIRED — the whole point of this feature is so
 * the applicant can be told why (see register_user()'s and
 * attempt_login()'s use of latest_account_decision()). Throws
 * InvalidArgumentException if the account doesn't exist, isn't currently
 * pending, or no reason was given.
 */
function reject_account(int $userId, string $reason, int $actorUserId): array
{
    $reason = trim($reason);
    if ($reason === '') {
        throw new InvalidArgumentException('Please enter a reason for rejecting this account.');
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new InvalidArgumentException('Account not found.');
    }
    if ($user['status'] !== 'pending_approval') {
        throw new InvalidArgumentException('This account is not awaiting approval.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$userId]);
        $pdo->prepare(
            'INSERT INTO account_approval_actions (user_id, action, reason, actor_user_id) VALUES (?, "rejected", ?, ?)'
        )->execute([$userId, $reason, $actorUserId]);
        audit_log('user', $userId, 'account_rejected', ['status' => $user['status']], ['status' => 'rejected', 'reason' => $reason]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $user['status'] = 'rejected';
    return $user;
}

/**
 * Notifies every active administrator that a new account is awaiting their
 * decision — called right after a successful self-registration (see
 * register_user()). Notification text is deliberately plain English, not
 * run through t(): stored notification content isn't translated anywhere
 * else in the app either (see e.g. add_project_member()'s notify_person()
 * call in includes/models/projects.php), since a notification is persisted
 * once at write time and may be read later by an admin using a different
 * locale than whatever was active when it was created.
 */
function notify_admins_of_pending_account(int $userId, string $fullName, string $email): void
{
    $stmt = db()->prepare(
        "SELECT u.id FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE r.name = ? AND u.status = 'active'"
    );
    $stmt->execute([ROLE_ADMIN]);
    foreach ($stmt->fetchAll() as $row) {
        notify(
            (int)$row['id'],
            'account_pending_approval',
            'New account pending approval',
            "$fullName ($email) has requested access and is awaiting your approval.",
            'user',
            $userId
        );
    }
}

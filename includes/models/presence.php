<?php
declare(strict_types=1);

/**
 * Lightweight "who's online" presence tracking for the topbar's
 * "Online (x)" widget. There's no real disconnect event over plain HTTP, so
 * presence is approximated by recency: every logged-in request bumps
 * users.last_seen_at (see touch_user_presence(), called once per request
 * from bootstrap.php), and anyone whose last_seen_at falls within
 * ONLINE_THRESHOLD_MINUTES is considered online. This intentionally mirrors
 * how most "green dot" presence indicators work elsewhere (Slack, etc.) —
 * good enough for "is this person around right now", not a guarantee.
 */

const AF_ONLINE_THRESHOLD_MINUTES = 5;

/**
 * Bumps the given user's last_seen_at to now — but only if it's stale by at
 * least a minute, so a user clicking around the app doesn't trigger a write
 * on every single request. The WHERE clause makes this a no-op query (no
 * row touched) on the vast majority of calls, which is cheap.
 */
function touch_user_presence(int $userId): void
{
    db()->prepare(
        "UPDATE users SET last_seen_at = NOW()
         WHERE id = ? AND (last_seen_at IS NULL OR last_seen_at < NOW() - INTERVAL 60 SECOND)"
    )->execute([$userId]);
}

/**
 * Everyone whose last_seen_at is within the online window, most-recently-seen
 * first. Deliberately org-wide (not filtered by project/task visibility
 * restrictions — see includes/permissions.php) since knowing who else is
 * currently using the app isn't project-sensitive information.
 */
function list_online_users(): array
{
    $stmt = db()->prepare(
        "SELECT id, full_name, email, last_seen_at
         FROM users
         WHERE status = 'active' AND last_seen_at IS NOT NULL
           AND last_seen_at >= NOW() - INTERVAL ? MINUTE
         ORDER BY last_seen_at DESC, full_name"
    );
    $stmt->execute([AF_ONLINE_THRESHOLD_MINUTES]);
    return $stmt->fetchAll();
}

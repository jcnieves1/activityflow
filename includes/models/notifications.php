<?php
declare(strict_types=1);

function notify(int $recipientUserId, string $type, string $title, ?string $body = null, ?string $entityType = null, ?int $entityId = null): void
{
    db()->prepare(
        'INSERT INTO notifications (recipient_user_id, type, title, body, related_entity_type, related_entity_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$recipientUserId, $type, $title, $body, $entityType, $entityId]);
}

/** Notify the user account linked to a person record, if any. Silently no-ops for people without logins. */
function notify_person(int $personId, string $type, string $title, ?string $body = null, ?string $entityType = null, ?int $entityId = null): void
{
    $stmt = db()->prepare('SELECT user_id FROM people WHERE id = ? AND user_id IS NOT NULL');
    $stmt->execute([$personId]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        notify((int)$userId, $type, $title, $body, $entityType, $entityId);
    }
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function list_notifications(int $userId, int $limit = 30): array
{
    $stmt = db()->prepare('SELECT * FROM notifications WHERE recipient_user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notification_read(int $userId, int $notificationId): bool
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_user_id = ?');
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

function mark_all_notifications_read(int $userId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ? AND is_read = 0')->execute([$userId]);
}

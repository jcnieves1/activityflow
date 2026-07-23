<?php
declare(strict_types=1);

/**
 * Generic audit trail writer. Call after any state-changing operation on an
 * audited entity (activities, projects, people, time_entries, project_members,
 * account recovery, etc). $old/$new should be associative arrays of just the
 * changed fields — they are stored as JSON.
 */
function audit_log(string $entityType, ?int $entityId, string $action, ?array $old = null, ?array $new = null): void
{
    $actorId = current_user()['id'] ?? null;
    db()->prepare(
        'INSERT INTO audit_logs (entity_type, entity_id, action, old_value, new_value, actor_user_id, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $entityType,
        $entityId,
        $action,
        $old !== null ? json_encode($old, JSON_UNESCAPED_SLASHES) : null,
        $new !== null ? json_encode($new, JSON_UNESCAPED_SLASHES) : null,
        $actorId,
        client_ip(),
    ]);
}

function audit_history(string $entityType, int $entityId): array
{
    $stmt = db()->prepare(
        'SELECT al.*, u.full_name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         WHERE al.entity_type = ? AND al.entity_id = ?
         ORDER BY al.created_at DESC'
    );
    $stmt->execute([$entityType, $entityId]);
    return $stmt->fetchAll();
}

/** Returns only the fields that changed between $before and $after (for compact audit records). */
function diff_fields(array $before, array $after): array
{
    $old = [];
    $new = [];
    foreach ($after as $key => $value) {
        if (!array_key_exists($key, $before)) {
            continue;
        }
        if ((string)$before[$key] !== (string)$value) {
            $old[$key] = $before[$key];
            $new[$key] = $value;
        }
    }
    return [$old, $new];
}

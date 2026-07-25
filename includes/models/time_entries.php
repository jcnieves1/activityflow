<?php
declare(strict_types=1);

/**
 * Timer + manual time entries. Business rule: only one active (running)
 * timer is allowed per employee at any time.
 */

function get_time_entry(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM time_entries WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function active_timer_for(int $personId): ?array
{
    $stmt = db()->prepare(
        'SELECT te.*, a.title AS activity_title FROM time_entries te
         JOIN activities a ON a.id = te.activity_id
         WHERE te.person_id = ? AND te.is_timer = 1 AND te.ended_at IS NULL
         ORDER BY te.started_at DESC LIMIT 1'
    );
    $stmt->execute([$personId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function start_timer(int $activityId, int $personId): array
{
    if (active_timer_for($personId)) {
        return ['ok' => false, 'error' => 'You already have an active timer running. Stop or pause it before starting another.'];
    }
    $stmt = db()->prepare(
        'INSERT INTO time_entries (activity_id, person_id, started_at, is_manual, is_timer) VALUES (?, ?, NOW(), 0, 1)'
    );
    $stmt->execute([$activityId, $personId]);
    $id = (int)db()->lastInsertId();

    update_activity_status($activityId, 'in_progress');
    audit_log('time_entry', $id, 'timer_started');

    return ['ok' => true, 'time_entry_id' => $id];
}

/** Pausing ends the current timer segment; resuming (start_timer again) opens a new segment for the same task. */
function pause_or_stop_timer(int $personId, bool $isFinalStop = false): array
{
    $entry = active_timer_for($personId);
    if (!$entry) {
        return ['ok' => false, 'error' => 'No active timer to stop.'];
    }
    $duration = (int)round((strtotime(now()) - strtotime($entry['started_at'])) / 60);
    db()->prepare('UPDATE time_entries SET ended_at = NOW(), duration_minutes = ? WHERE id = ?')
        ->execute([max(0, $duration), $entry['id']]);

    audit_log('time_entry', $entry['id'], $isFinalStop ? 'timer_stopped' : 'timer_paused');

    return ['ok' => true, 'duration_minutes' => max(0, $duration), 'activity_id' => $entry['activity_id']];
}

function manual_time_entry(int $activityId, int $personId, string $startedAt, ?string $endedAt, ?int $durationMinutes, ?string $notes): array
{
    if ($durationMinutes === null && $endedAt) {
        $durationMinutes = (int)round((strtotime($endedAt) - strtotime($startedAt)) / 60);
    }
    if ($durationMinutes !== null && $durationMinutes < 0) {
        return ['ok' => false, 'error' => 'Time entries cannot have a negative duration.'];
    }

    $stmt = db()->prepare(
        'INSERT INTO time_entries (activity_id, person_id, started_at, ended_at, duration_minutes, is_manual, is_timer, notes)
         VALUES (?, ?, ?, ?, ?, 1, 0, ?)'
    );
    $stmt->execute([$activityId, $personId, $startedAt, $endedAt ?: null, $durationMinutes, $notes]);
    $id = (int)db()->lastInsertId();
    audit_log('time_entry', $id, 'manual_entry_created', null, ['duration_minutes' => $durationMinutes]);

    return ['ok' => true, 'time_entry_id' => $id];
}

function update_time_entry(int $id, array $data): array
{
    $stmt = db()->prepare('SELECT * FROM time_entries WHERE id = ?');
    $stmt->execute([$id]);
    $before = $stmt->fetch();
    if (!$before) {
        return ['ok' => false, 'error' => 'Time entry not found.'];
    }

    $duration = $data['duration_minutes'] ?? $before['duration_minutes'];
    if ($duration !== null && (int)$duration < 0) {
        return ['ok' => false, 'error' => 'Time entries cannot have a negative duration.'];
    }

    db()->prepare('UPDATE time_entries SET started_at=?, ended_at=?, duration_minutes=?, notes=? WHERE id=?')
        ->execute([
            $data['started_at'] ?? $before['started_at'],
            $data['ended_at'] ?? $before['ended_at'],
            $duration,
            $data['notes'] ?? $before['notes'],
            $id,
        ]);

    [$old, $new] = diff_fields($before, $data);
    audit_log('time_entry', $id, 'edited', $old, $new);

    return ['ok' => true];
}

function list_time_entries_for_activity(int $activityId): array
{
    $stmt = db()->prepare(
        'SELECT te.*, p.full_name AS person_name FROM time_entries te
         JOIN people p ON p.id = te.person_id WHERE te.activity_id = ? ORDER BY te.started_at DESC'
    );
    $stmt->execute([$activityId]);
    return $stmt->fetchAll();
}

function activity_time_totals(int $activityId): array
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(duration_minutes),0) AS actual FROM time_entries WHERE activity_id = ?');
    $stmt->execute([$activityId]);
    $actual = (int)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT estimated_minutes FROM activities WHERE id = ?');
    $stmt->execute([$activityId]);
    $estimated = (int)($stmt->fetchColumn() ?: 0);

    return ['estimated_minutes' => $estimated, 'actual_minutes' => $actual];
}

// ---------------------------------------------------------------------
// Interruptions
// ---------------------------------------------------------------------

function record_interruption(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO interruptions
            (interrupting_activity_id, interrupted_activity_id, started_at, ended_at, time_lost_minutes, was_resumed, impact_on_target_date, notes)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $data['interrupting_activity_id'],
        nz($data, 'interrupted_activity_id'),
        nz($data, 'started_at'),
        nz($data, 'ended_at'),
        nz($data, 'time_lost_minutes'),
        isset($data['was_resumed']) ? (int)(bool)$data['was_resumed'] : null,
        $data['impact_on_target_date'] ?? null,
        $data['notes'] ?? null,
    ]);
    $id = (int)db()->lastInsertId();
    audit_log('interruption', $id, 'created', null, $data);
    return $id;
}

/**
 * Interruption rows involving this activity from EITHER side, with both
 * possible directions' related-task info joined in so the caller never needs
 * a second query no matter which side it's rendering for:
 *   - interrupted_title/interrupted_status: the PLANNED task that got
 *     interrupted (used when this activity is the interrupter — "which task
 *     did I interrupt?").
 *   - interrupting_title/interrupting_status/interrupting_assignee_name: the
 *     UNPLANNED task that did the interrupting (used when this activity is
 *     the one that got interrupted — "what interrupted me, and who logged it?").
 */
function list_interruptions_for_activity(int $activityId): array
{
    $stmt = db()->prepare(
        'SELECT i.*,
                ai.title AS interrupted_title, ai.status AS interrupted_status,
                ag.title AS interrupting_title, ag.status AS interrupting_status,
                agp.full_name AS interrupting_assignee_name
         FROM interruptions i
         LEFT JOIN activities ai ON ai.id = i.interrupted_activity_id
         LEFT JOIN activities ag ON ag.id = i.interrupting_activity_id
         LEFT JOIN people agp ON agp.id = ag.assignee_id
         WHERE i.interrupting_activity_id = ? OR i.interrupted_activity_id = ?'
    );
    $stmt->execute([$activityId, $activityId]);
    return $stmt->fetchAll();
}

/**
 * Given a set of activity ids, returns the subset that were interrupted by
 * some other (unplanned) task at least once — i.e. they appear as the
 * "victim" side of an interruptions row. Used by My Tasks to flag a task
 * with an indicator without an N+1 query per row.
 */
function activity_ids_that_were_interrupted(array $activityIds): array
{
    $activityIds = array_values(array_unique(array_map('intval', $activityIds)));
    if (!$activityIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
    $stmt = db()->prepare(
        "SELECT DISTINCT interrupted_activity_id FROM interruptions WHERE interrupted_activity_id IN ($placeholders)"
    );
    $stmt->execute($activityIds);
    return array_map('intval', array_column($stmt->fetchAll(), 'interrupted_activity_id'));
}

<?php
declare(strict_types=1);

/**
 * Vacations: each row is one consecutive block of time off for a person
 * (non-consecutive days off are separate rows — see create_vacation()'s
 * overlap guard, which also prevents two rows for the same person from ever
 * describing overlapping dates). Visible to everyone (the Vacations page and
 * its calendar); only an administrator or the vacationing person themselves
 * may create/edit/delete a given row — see can_manage_vacation() in
 * includes/permissions.php, enforced by api/vacations.php.
 *
 * Tasks are never blocked from being scheduled during someone's vacation —
 * this is a warning surfaced to the user, not a hard constraint — see
 * activity_vacation_conflict() / bulk_activity_vacation_conflicts() below,
 * used by the activity modal (live check) and task list pages (persisted
 * badge), and list_vacation_task_conflicts() which powers the Vacations
 * page's "colliding" list.
 */

function list_vacations(array $filters = []): array
{
    $sql = 'SELECT v.*, p.full_name AS person_name
            FROM vacations v
            JOIN people p ON p.id = v.person_id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['person_id_in']) && is_array($filters['person_id_in'])) {
        $ids = array_values(array_filter(array_map('intval', $filters['person_id_in'])));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND v.person_id IN ($placeholders)";
            array_push($params, ...$ids);
        }
    }
    // Overlap against a viewing window (e.g. the month/year currently shown
    // on the calendar) rather than an exact match, so a vacation that spans
    // across the boundary of the window still shows up.
    if (!empty($filters['date_from'])) {
        $sql .= ' AND v.end_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND v.start_date <= ?';
        $params[] = $filters['date_to'];
    }
    $sql .= ' ORDER BY v.start_date, v.person_id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_vacation(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT v.*, p.full_name AS person_name FROM vacations v JOIN people p ON p.id = v.person_id WHERE v.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_vacations_for_person(int $personId): array
{
    return list_vacations(['person_id_in' => [$personId]]);
}

/** The first vacation row for $personId whose [start_date,end_date] overlaps [$start,$end], or null. $excludeId skips a row (used when editing that row itself). */
function find_overlapping_vacation(int $personId, string $start, string $end, ?int $excludeId = null): ?array
{
    if ($start === '' || $end === '') {
        return null;
    }
    $sql = 'SELECT v.*, p.full_name AS person_name FROM vacations v JOIN people p ON p.id = v.person_id
            WHERE v.person_id = ? AND v.start_date <= ? AND v.end_date >= ?';
    $params = [$personId, $end, $start];
    if ($excludeId !== null) {
        $sql .= ' AND v.id != ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_vacation(array $data, int $createdBy): int
{
    $personId = (int)($data['person_id'] ?? 0);
    if (!$personId) {
        throw new InvalidArgumentException('Person is required.');
    }
    $start = (string)($data['start_date'] ?? '');
    $end = (string)($data['end_date'] ?? '');
    if ($start === '' || $end === '') {
        throw new InvalidArgumentException('Start date and end date are required.');
    }
    if ($start > $end) {
        throw new InvalidArgumentException('The start date must be on or before the end date.');
    }
    if (find_overlapping_vacation($personId, $start, $end)) {
        throw new InvalidArgumentException('This person already has a vacation entry that overlaps these dates. Edit the existing entry instead, or pick a non-overlapping range.');
    }
    $notes = trim((string)($data['notes'] ?? ''));

    db()->prepare('INSERT INTO vacations (person_id, start_date, end_date, notes, created_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$personId, $start, $end, $notes !== '' ? $notes : null, $createdBy]);
    $id = (int)db()->lastInsertId();
    audit_log('vacation', $id, 'created', null, ['person_id' => $personId, 'start_date' => $start, 'end_date' => $end]);
    return $id;
}

function update_vacation(int $id, array $data): void
{
    $before = get_vacation($id);
    if (!$before) {
        throw new RuntimeException('Vacation entry not found.');
    }
    // The person a vacation belongs to is fixed at creation — editing only
    // ever adjusts dates/notes for the same person, never reassigns it to
    // someone else (that would just be a different person's entry).
    $personId = (int)$before['person_id'];
    $start = (string)($data['start_date'] ?? '');
    $end = (string)($data['end_date'] ?? '');
    if ($start === '' || $end === '') {
        throw new InvalidArgumentException('Start date and end date are required.');
    }
    if ($start > $end) {
        throw new InvalidArgumentException('The start date must be on or before the end date.');
    }
    if (find_overlapping_vacation($personId, $start, $end, $id)) {
        throw new InvalidArgumentException('This person already has another vacation entry that overlaps these dates.');
    }
    $notes = trim((string)($data['notes'] ?? ''));

    db()->prepare('UPDATE vacations SET start_date=?, end_date=?, notes=? WHERE id=?')
        ->execute([$start, $end, $notes !== '' ? $notes : null, $id]);
    [$old, $new] = diff_fields($before, ['start_date' => $start, 'end_date' => $end, 'notes' => $notes]);
    audit_log('vacation', $id, 'updated', $old, $new);
}

function delete_vacation(int $id): void
{
    $vacation = get_vacation($id);
    if (!$vacation) {
        throw new RuntimeException('Vacation entry not found.');
    }
    db()->prepare('DELETE FROM vacations WHERE id = ?')->execute([$id]);
    audit_log('vacation', $id, 'deleted', [
        'person_id' => $vacation['person_id'], 'start_date' => $vacation['start_date'], 'end_date' => $vacation['end_date'],
    ], null);
}

/**
 * Does this activity's assignee have a vacation overlapping the task's
 * scheduled dates? Uses whichever dates the task actually has — planned
 * dates if set (the normal "assigning/editing the days for a task" case),
 * falling back to actual start/completion (covers unplanned/ad-hoc tasks,
 * which have no planned_start_at/target_completion_at of their own). A task
 * with neither pair of dates set can't conflict with anything.
 */
function activity_vacation_conflict(array $activity): ?array
{
    if (empty($activity['assignee_id'])) {
        return null;
    }
    $start = $activity['planned_start_at'] ?? $activity['actual_start_at'] ?? null;
    $end = $activity['target_completion_at'] ?? $activity['actual_completion_at'] ?? null;
    if (!$start || !$end) {
        return null;
    }
    return find_overlapping_vacation((int)$activity['assignee_id'], substr($start, 0, 10), substr($end, 0, 10));
}

/**
 * Bulk version of activity_vacation_conflict() for list pages (My Tasks,
 * Team Activities, Task Board) — one query instead of one per row. Returns
 * activity_id => ['vacation_id', 'start_date', 'end_date'] for every
 * activity currently conflicting with its assignee's vacation.
 */
function bulk_activity_vacation_conflicts(array $activityIds): array
{
    $activityIds = array_values(array_unique(array_map('intval', $activityIds)));
    if (!$activityIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
    $sql = "SELECT a.id AS activity_id, v.id AS vacation_id, v.start_date, v.end_date
            FROM activities a
            JOIN vacations v ON v.person_id = a.assignee_id
                AND DATE(COALESCE(a.planned_start_at, a.actual_start_at)) <= v.end_date
                AND DATE(COALESCE(a.target_completion_at, a.actual_completion_at)) >= v.start_date
            WHERE a.id IN ($placeholders)
              AND COALESCE(a.planned_start_at, a.actual_start_at) IS NOT NULL
              AND COALESCE(a.target_completion_at, a.actual_completion_at) IS NOT NULL";
    $stmt = db()->prepare($sql);
    $stmt->execute($activityIds);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int)$row['activity_id']] = [
            'vacation_id' => (int)$row['vacation_id'], 'start_date' => $row['start_date'], 'end_date' => $row['end_date'],
        ];
    }
    return $map;
}

/**
 * Every currently-active (non-cancelled) task that collides with its
 * assignee's vacation — powers the Vacations page's "colliding" list.
 * $filters supports person_id_in to match the page's top filter.
 */
function list_vacation_task_conflicts(array $filters = []): array
{
    $sql = "SELECT a.id AS activity_id, a.title, a.status, a.activity_type, a.project_id,
                   a.planned_start_at, a.target_completion_at, a.actual_start_at, a.actual_completion_at,
                   pr.name AS project_name,
                   p.id AS person_id, p.full_name AS person_name,
                   v.id AS vacation_id, v.start_date AS vacation_start, v.end_date AS vacation_end, v.notes AS vacation_notes
            FROM activities a
            JOIN vacations v ON v.person_id = a.assignee_id
                AND DATE(COALESCE(a.planned_start_at, a.actual_start_at)) <= v.end_date
                AND DATE(COALESCE(a.target_completion_at, a.actual_completion_at)) >= v.start_date
            JOIN people p ON p.id = a.assignee_id
            LEFT JOIN projects pr ON pr.id = a.project_id
            WHERE a.status != 'cancelled'
              AND COALESCE(a.planned_start_at, a.actual_start_at) IS NOT NULL
              AND COALESCE(a.target_completion_at, a.actual_completion_at) IS NOT NULL";
    $params = [];

    if (!empty($filters['person_id_in']) && is_array($filters['person_id_in'])) {
        $ids = array_values(array_filter(array_map('intval', $filters['person_id_in'])));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND a.assignee_id IN ($placeholders)";
            array_push($params, ...$ids);
        }
    }
    $sql .= ' ORDER BY v.start_date DESC, a.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

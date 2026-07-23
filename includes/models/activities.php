<?php
declare(strict_types=1);

/**
 * The central activity model — planned work, unplanned/ad-hoc requests, and
 * project tasks all live in the `activities` table, distinguished by
 * activity_type / is_adhoc / project_id.
 */

const ACTIVITY_STATUSES  = ['backlog', 'planned', 'ready', 'in_progress', 'blocked', 'waiting', 'completed', 'cancelled'];
const ACTIVITY_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
const REQUEST_CHANNELS = [
    'manager_request', 'coworker_request', 'customer_request', 'meeting',
    'chat', 'phone', 'walk_up', 'system_incident', 'self_initiated', 'other',
];

function activity_select_base(): string
{
    return 'SELECT a.*, pr.name AS project_name, pr.color AS project_color, pr.code AS project_code,
                    asg.full_name AS assignee_name, req.full_name AS requester_name,
                    c.name AS category_name, u.full_name AS created_by_name
             FROM activities a
             LEFT JOIN projects pr ON pr.id = a.project_id
             LEFT JOIN people asg ON asg.id = a.assignee_id
             LEFT JOIN people req ON req.id = a.requester_id
             LEFT JOIN activity_categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.is_active = 1';
}

function list_activities(array $filters = []): array
{
    $sql = activity_select_base();
    $params = [];

    $map = [
        'assignee_id'    => 'a.assignee_id = ?',
        'requester_id'   => 'a.requester_id = ?',
        'project_id'     => 'a.project_id = ?',
        'status'         => 'a.status = ?',
        'priority'       => 'a.priority = ?',
        'activity_type'  => 'a.activity_type = ?',
        'category_id'    => 'a.category_id = ?',
        'request_channel'=> 'a.request_channel = ?',
    ];
    foreach ($map as $key => $clause) {
        if (!empty($filters[$key])) {
            $sql .= " AND $clause";
            $params[] = $filters[$key];
        }
    }
    if (!empty($filters['assignee_id_in']) && is_array($filters['assignee_id_in'])) {
        $ids = array_values(array_unique(array_map('intval', $filters['assignee_id_in'])));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND a.assignee_id IN ($placeholders)";
            array_push($params, ...$ids);
        }
    }
    if (!empty($filters['status_in']) && is_array($filters['status_in'])) {
        $statuses = array_values(array_intersect(ACTIVITY_STATUSES, $filters['status_in']));
        if ($statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $sql .= " AND a.status IN ($placeholders)";
            array_push($params, ...$statuses);
        }
    }
    if (isset($filters['is_adhoc']) && $filters['is_adhoc'] !== '') {
        $sql .= ' AND a.is_adhoc = ?';
        $params[] = (int)$filters['is_adhoc'];
    }
    if (!empty($filters['no_project'])) {
        $sql .= ' AND a.project_id IS NULL';
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND COALESCE(a.planned_start_at, a.requested_at) >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND COALESCE(a.planned_start_at, a.requested_at) <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (a.title LIKE ? OR a.description LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        array_push($params, $like, $like);
    }
    $sql .= ' ORDER BY ' . ($filters['order_by'] ?? 'a.created_at DESC');
    if (!empty($filters['limit'])) {
        $sql .= ' LIMIT ' . (int)$filters['limit'];
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_activity(int $id): ?array
{
    $stmt = db()->prepare(activity_select_base() . ' AND a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Permanently deletes a task. Existing ON DELETE CASCADE foreign keys take care
 * of its comments, time entries, tags, dependencies, status history, and
 * interruptions (see schema.sql); subtasks that had this task as their parent
 * are kept but detached (parent_activity_id is set NULL by the same FK rule)
 * rather than deleted themselves. There is no undo — the caller must obtain
 * explicit, informed confirmation before calling this.
 */
function delete_activity(int $id): bool
{
    $activity = get_activity($id);
    if (!$activity) {
        return false;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM activity_comments WHERE activity_id = ?');
    $stmt->execute([$id]);
    $commentCount = (int)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT COUNT(*) FROM time_entries WHERE activity_id = ?');
    $stmt->execute([$id]);
    $timeEntryCount = (int)$stmt->fetchColumn();

    audit_log('activity', $id, 'deleted', [
        'title' => $activity['title'],
        'project_id' => $activity['project_id'],
        'comment_count' => $commentCount,
        'time_entry_count' => $timeEntryCount,
    ], null);

    db()->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
    return true;
}

function validate_activity_input(array $data): array
{
    $errors = [];
    if (empty($data['title'])) {
        $errors[] = 'Title is required.';
    }
    if (empty($data['assignee_id'])) {
        $errors[] = 'An assignee is required.';
    }
    if (empty($data['requester_id'])) {
        $errors[] = 'A requester is required.';
    }
    if (!empty($data['activity_type']) && !in_array($data['activity_type'], ['planned', 'unplanned'], true)) {
        $errors[] = 'Invalid activity type.';
    }
    if (!empty($data['status']) && !in_array($data['status'], ACTIVITY_STATUSES, true)) {
        $errors[] = 'Invalid status.';
    }
    // Business rule: planned project tasks require a project.
    if (($data['activity_type'] ?? 'planned') === 'planned' && !empty($data['requires_project']) && empty($data['project_id'])) {
        $errors[] = 'Planned project tasks require a project.';
    }
    return $errors;
}

/**
 * @param string $activityType 'planned' or 'unplanned' — set by the caller based on how the
 *   record was created (My Day planning vs. quick-add), never guessed here.
 */
function create_activity(array $data, string $activityType, int $createdByUserId, bool $isAdhoc = false): int
{
    $status = $data['status'] ?? ($activityType === 'unplanned' ? 'in_progress' : 'planned');
    $requestedAt = $data['requested_at'] ?? now();

    $stmt = db()->prepare(
        'INSERT INTO activities
            (title, description, activity_type, is_adhoc, project_id, parent_activity_id, assignee_id, requester_id,
             created_by, requested_at, planned_start_at, target_completion_at, estimated_minutes, priority, status,
             completion_pct, category_id, interruption_reason, request_channel, notes, original_classification, is_milestone)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $activityType,
        $isAdhoc ? 1 : 0,
        nz($data, 'project_id'),
        nz($data, 'parent_activity_id'),
        $data['assignee_id'],
        $data['requester_id'],
        $createdByUserId,
        $requestedAt,
        nz($data, 'planned_start_at'),
        nz($data, 'target_completion_at'),
        nz($data, 'estimated_minutes'),
        $data['priority'] ?? 'normal',
        $status,
        nz($data, 'category_id'),
        $data['interruption_reason'] ?? null,
        $data['request_channel'] ?? null,
        $data['notes'] ?? null,
        $activityType, // original_classification preserved permanently
        !empty($data['is_milestone']) ? 1 : 0,
    ]);
    $id = (int)db()->lastInsertId();

    audit_log('activity', $id, 'created', null, ['title' => $data['title'], 'activity_type' => $activityType, 'is_adhoc' => $isAdhoc]);

    if ((int)$data['assignee_id'] !== (int)$data['requester_id']) {
        notify_person((int)$data['assignee_id'], 'new_assignment', 'New task assigned: ' . $data['title'],
            $activityType === 'unplanned' ? 'Last-minute request' : 'Planned task', 'activity', $id);
    }
    if ($activityType === 'unplanned') {
        notify_person((int)$data['assignee_id'], 'unplanned_request', 'New last-minute request: ' . $data['title'], null, 'activity', $id);
    }

    return $id;
}

/**
 * Duplicates a task's descriptive fields and tags into a brand-new task, in the
 * same or a different project. Comments, time entries, and audit history are
 * deliberately NOT copied — a clone is a fresh, unstarted copy of what the task
 * IS, not a duplicate of the original's progress. It's always created as a
 * top-level task (no parent_activity_id): the source's parent may belong to a
 * different project, or may not make sense in the destination.
 *
 * Builds on create_activity() rather than a bespoke INSERT so it automatically
 * gets the same status/notification defaults as any other new task.
 */
function clone_activity(array $source, int $targetProjectId, int $createdByUserId): int
{
    $data = [
        'title' => $source['title'] . ' (Copy)',
        'description' => $source['description'],
        'project_id' => $targetProjectId,
        'assignee_id' => $source['assignee_id'],
        'requester_id' => $source['requester_id'],
        'planned_start_at' => $source['planned_start_at'],
        'target_completion_at' => $source['target_completion_at'],
        'estimated_minutes' => $source['estimated_minutes'],
        'priority' => $source['priority'],
        'category_id' => $source['category_id'],
        'request_channel' => $source['request_channel'],
        'notes' => $source['notes'],
        'is_milestone' => $source['is_milestone'],
    ];

    $newId = create_activity($data, $source['activity_type'], $createdByUserId, (bool)$source['is_adhoc']);

    $tags = get_activity_tags((int)$source['id']);
    if ($tags) {
        set_activity_tags($newId, $tags);
    }

    audit_log('activity', $newId, 'cloned_from', null, ['source_activity_id' => (int)$source['id']]);

    return $newId;
}

/**
 * Reassigns a task to a different project in place — its comments, time
 * entries, and history all stay attached, only project_id changes.
 */
function move_activity_to_project(int $activityId, int $targetProjectId): void
{
    $before = get_activity($activityId);
    if (!$before) {
        return;
    }
    db()->prepare('UPDATE activities SET project_id = ? WHERE id = ?')->execute([$targetProjectId, $activityId]);
    audit_log('activity', $activityId, 'moved_to_project',
        ['project_id' => $before['project_id']], ['project_id' => $targetProjectId]);
}

/** Update editable fields; preserves activity_type/original_classification (use reclassify_activity for that). */
function update_activity(int $id, array $data): void
{
    $before = get_activity($id);
    if (!$before) {
        throw new RuntimeException('Activity not found.');
    }

    $scheduleChanged = (
        ($data['planned_start_at'] ?? null) !== $before['planned_start_at']
        || ($data['target_completion_at'] ?? null) !== $before['target_completion_at']
    );

    $parentId = $data['parent_activity_id'] ?? null;
    if ((int)$parentId === (int)$id) {
        $parentId = null; // a task cannot be its own parent
    }

    $stmt = db()->prepare(
        'UPDATE activities SET title=?, description=?, project_id=?, parent_activity_id=?, assignee_id=?, requester_id=?,
            planned_start_at=?, target_completion_at=?, estimated_minutes=?, priority=?, category_id=?,
            interruption_reason=?, request_channel=?, notes=?, is_milestone=? WHERE id=?'
    );
    $stmt->execute([
        $data['title'], $data['description'] ?? null, nz($data, 'project_id'), $parentId ?: null,
        $data['assignee_id'], $data['requester_id'], nz($data, 'planned_start_at'),
        nz($data, 'target_completion_at'), nz($data, 'estimated_minutes'),
        $data['priority'] ?? 'normal', nz($data, 'category_id'), $data['interruption_reason'] ?? null,
        $data['request_channel'] ?? null, $data['notes'] ?? null, !empty($data['is_milestone']) ? 1 : 0, $id,
    ]);

    if ($scheduleChanged) {
        db()->prepare(
            'INSERT INTO activity_schedule_history
                (activity_id, old_planned_start_at, old_target_completion_at, new_planned_start_at, new_target_completion_at, changed_by)
             VALUES (?,?,?,?,?,?)'
        )->execute([
            $id, $before['planned_start_at'], $before['target_completion_at'],
            nz($data, 'planned_start_at'), nz($data, 'target_completion_at'),
            current_user()['id'] ?? null,
        ]);
    }

    [$old, $new] = diff_fields($before, $data);
    audit_log('activity', $id, 'updated', $old, $new);
}

function update_activity_status(int $id, string $status, ?int $completionPct = null): void
{
    if (!in_array($status, ACTIVITY_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid status.');
    }
    $before = get_activity($id);
    if (!$before) {
        throw new RuntimeException('Activity not found.');
    }

    $pct = $completionPct ?? (int)$before['completion_pct'];
    if ($status === 'completed') {
        $pct = 100;
    }

    $actualStart = $before['actual_start_at'];
    if ($status === 'in_progress' && !$actualStart) {
        $actualStart = now();
    }
    $actualCompletion = $before['actual_completion_at'];
    if ($status === 'completed' && !$actualCompletion) {
        $actualCompletion = now();
    }

    db()->prepare(
        'UPDATE activities SET status=?, completion_pct=?, actual_start_at=?, actual_completion_at=? WHERE id=?'
    )->execute([$status, $pct, $actualStart, $actualCompletion, $id]);

    audit_log('activity', $id, 'status_changed', ['status' => $before['status']], ['status' => $status]);

    if ($status === 'blocked') {
        notify_person((int)$before['assignee_id'], 'task_blocked', 'Task blocked: ' . $before['title'], null, 'activity', $id);
    } else {
        notify_person((int)$before['assignee_id'], 'status_changed', 'Task status updated: ' . $before['title'], status_label($status), 'activity', $id);
    }
}

function update_activity_progress(int $id, int $pct): void
{
    $pct = max(0, min(100, $pct));
    $before = get_activity($id);
    db()->prepare('UPDATE activities SET completion_pct = ? WHERE id = ?')->execute([$pct, $id]);
    audit_log('activity', $id, 'progress_changed', ['completion_pct' => $before['completion_pct']], ['completion_pct' => $pct]);
}

/**
 * Reclassification (planned <-> unplanned) is never silent: a reason is
 * required and the original classification is preserved in original_classification.
 */
function reclassify_activity(int $id, string $newType, string $reason, int $actorUserId): void
{
    if (!in_array($newType, ['planned', 'unplanned'], true)) {
        throw new InvalidArgumentException('Invalid classification.');
    }
    if (trim($reason) === '') {
        throw new InvalidArgumentException('A reason is required to reclassify an activity.');
    }
    $before = get_activity($id);
    if (!$before) {
        throw new RuntimeException('Activity not found.');
    }

    db()->prepare(
        'UPDATE activities SET activity_type=?, reclassified_at=NOW(), reclassified_by=?, reclassification_reason=? WHERE id=?'
    )->execute([$newType, $actorUserId, $reason, $id]);

    audit_log('activity', $id, 'reclassified',
        ['activity_type' => $before['activity_type']],
        ['activity_type' => $newType, 'reason' => $reason]);
}

function reorder_activities(array $orderedIds): void
{
    $stmt = db()->prepare('UPDATE activities SET sort_order = ? WHERE id = ?');
    foreach ($orderedIds as $index => $id) {
        $stmt->execute([$index, (int)$id]);
    }
}

function copy_activity_to_date(int $id, string $newDate, int $createdByUserId): int
{
    $orig = get_activity($id);
    if (!$orig) {
        throw new RuntimeException('Activity not found.');
    }
    $time = $orig['planned_start_at'] ? date('H:i:s', strtotime($orig['planned_start_at'])) : '09:00:00';
    return create_activity([
        'title' => $orig['title'],
        'description' => $orig['description'],
        'project_id' => $orig['project_id'],
        'assignee_id' => $orig['assignee_id'],
        'requester_id' => $orig['requester_id'],
        'planned_start_at' => "$newDate $time",
        'target_completion_at' => $orig['target_completion_at'],
        'estimated_minutes' => $orig['estimated_minutes'],
        'priority' => $orig['priority'],
        'category_id' => $orig['category_id'],
        'request_channel' => $orig['request_channel'],
        'notes' => $orig['notes'],
    ], $orig['activity_type'], $createdByUserId, (bool)$orig['is_adhoc']);
}

// ---------------------------------------------------------------------
// My Day queries
// ---------------------------------------------------------------------

function my_day_activities(int $personId, string $date): array
{
    $stmt = db()->prepare(
        activity_select_base() . ' AND a.assignee_id = ? AND DATE(a.planned_start_at) = ? ORDER BY a.sort_order, a.planned_start_at'
    );
    $stmt->execute([$personId, $date]);
    return $stmt->fetchAll();
}

function my_day_unplanned(int $personId, string $date): array
{
    $stmt = db()->prepare(
        activity_select_base() . ' AND a.assignee_id = ? AND a.activity_type = "unplanned"
         AND DATE(COALESCE(a.planned_start_at, a.requested_at)) = ? ORDER BY a.requested_at'
    );
    $stmt->execute([$personId, $date]);
    return $stmt->fetchAll();
}

function my_day_backlog(int $personId): array
{
    $stmt = db()->prepare(
        activity_select_base() . ' AND a.assignee_id = ? AND a.status IN ("backlog","ready") AND a.planned_start_at IS NULL
         ORDER BY FIELD(a.priority,"urgent","high","normal","low"), a.created_at'
    );
    $stmt->execute([$personId]);
    return $stmt->fetchAll();
}

function my_day_completed(int $personId, string $date): array
{
    $stmt = db()->prepare(
        activity_select_base() . ' AND a.assignee_id = ? AND a.status = "completed" AND DATE(a.actual_completion_at) = ?
         ORDER BY a.actual_completion_at DESC'
    );
    $stmt->execute([$personId, $date]);
    return $stmt->fetchAll();
}

function my_day_carried_over(int $personId, string $date): array
{
    $stmt = db()->prepare(
        activity_select_base() . ' AND a.assignee_id = ? AND a.status NOT IN ("completed","cancelled")
         AND a.planned_start_at IS NOT NULL AND DATE(a.planned_start_at) < ? ORDER BY a.planned_start_at'
    );
    $stmt->execute([$personId, $date]);
    return $stmt->fetchAll();
}

function my_day_hours_summary(int $personId, string $date): array
{
    $stmt = db()->prepare(
        'SELECT activity_type, COALESCE(SUM(estimated_minutes),0) AS minutes FROM activities
         WHERE assignee_id = ? AND DATE(COALESCE(planned_start_at, requested_at)) = ? AND is_active = 1
         GROUP BY activity_type'
    );
    $stmt->execute([$personId, $date]);
    $rows = $stmt->fetchAll();
    $planned = 0;
    $unplanned = 0;
    foreach ($rows as $r) {
        if ($r['activity_type'] === 'planned') {
            $planned = (int)$r['minutes'];
        } else {
            $unplanned = (int)$r['minutes'];
        }
    }
    return ['planned_minutes' => $planned, 'unplanned_minutes' => $unplanned];
}

// ---------------------------------------------------------------------
// Comments, tags, dependencies
// ---------------------------------------------------------------------

function add_activity_comment(int $activityId, int $authorUserId, string $body): int
{
    db()->prepare('INSERT INTO activity_comments (activity_id, author_id, body) VALUES (?, ?, ?)')
        ->execute([$activityId, $authorUserId, $body]);
    $id = (int)db()->lastInsertId();
    audit_log('activity', $activityId, 'comment_added');
    return $id;
}

function list_activity_comments(int $activityId): array
{
    $stmt = db()->prepare(
        'SELECT c.*, u.full_name AS author_name FROM activity_comments c
         JOIN users u ON u.id = c.author_id WHERE c.activity_id = ? ORDER BY c.created_at'
    );
    $stmt->execute([$activityId]);
    return $stmt->fetchAll();
}

function get_activity_comment(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT c.*, u.full_name AS author_name FROM activity_comments c
         JOIN users u ON u.id = c.author_id WHERE c.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function update_activity_comment(int $id, string $body): void
{
    $before = get_activity_comment($id);
    db()->prepare('UPDATE activity_comments SET body = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$body, $id]);
    if ($before) {
        audit_log('activity', (int)$before['activity_id'], 'comment_edited',
            ['body' => $before['body']], ['body' => $body]);
    }
}

function set_activity_tags(int $activityId, array $tagNames): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM activity_tags WHERE activity_id = ?')->execute([$activityId]);
    $findTag = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
    $insertTag = $pdo->prepare('INSERT INTO tags (name) VALUES (?)');
    $link = $pdo->prepare('INSERT IGNORE INTO activity_tags (activity_id, tag_id) VALUES (?, ?)');
    foreach ($tagNames as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        $findTag->execute([$name]);
        $tagId = $findTag->fetchColumn();
        if (!$tagId) {
            $insertTag->execute([$name]);
            $tagId = $pdo->lastInsertId();
        }
        $link->execute([$activityId, $tagId]);
    }
}

function get_activity_tags(int $activityId): array
{
    $stmt = db()->prepare(
        'SELECT t.name FROM tags t JOIN activity_tags at ON at.tag_id = t.id WHERE at.activity_id = ?'
    );
    $stmt->execute([$activityId]);
    return array_column($stmt->fetchAll(), 'name');
}

function add_activity_dependency(int $activityId, int $dependsOnId): void
{
    db()->prepare('INSERT IGNORE INTO activity_dependencies (activity_id, depends_on_activity_id) VALUES (?, ?)')
        ->execute([$activityId, $dependsOnId]);
}

function list_activity_dependencies(int $activityId): array
{
    $stmt = db()->prepare(
        'SELECT a.id, a.title, a.status FROM activity_dependencies d
         JOIN activities a ON a.id = d.depends_on_activity_id WHERE d.activity_id = ?'
    );
    $stmt->execute([$activityId]);
    return $stmt->fetchAll();
}

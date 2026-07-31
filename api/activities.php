<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');
$user = current_user();

if ($method === 'GET' && $action === 'list') {
    $filters = $_GET;
    // order_by is only ever set by trusted server-side callers (never from client input) to avoid SQL injection via ORDER BY.
    unset($filters['action'], $filters['order_by']);
    $activities = list_activities($filters);
    // Defense in depth: every page that renders a task list already scopes
    // its own list_activities() call appropriately, but this generic
    // endpoint is reachable directly by URL, so a restricted role must be
    // scoped here too rather than relying solely on callers behaving.
    if (!has_broad_project_visibility()) {
        $activities = array_values(array_filter($activities, 'activity_is_visible'));
    }
    json_response(['ok' => true, 'activities' => $activities]);
}

if ($method === 'GET' && $action === 'get') {
    $activity = get_activity((int)($_GET['id'] ?? 0));
    if (!$activity) json_error('Activity not found.', 404);
    if (!activity_is_visible($activity)) {
        deny('You do not have access to this activity.');
    }
    $activity['comments'] = list_activity_comments((int)$activity['id']);
    $activity['tags'] = get_activity_tags((int)$activity['id']);
    $activity['time_entries'] = list_time_entries_for_activity((int)$activity['id']);
    $activity['time_totals'] = activity_time_totals((int)$activity['id']);
    $activity['interruptions'] = list_interruptions_for_activity((int)$activity['id']);
    $activity['dependencies'] = list_activity_dependencies((int)$activity['id']);
    $activity['history'] = audit_history('activity', (int)$activity['id']);
    // Computed server-side so the client can decide which buttons to show without
    // re-implementing (and risking drifting out of sync with) the permission
    // rules in JS. can_edit also gates the Clone/Move buttons — cloning or
    // moving a task out of its current context requires being able to edit it,
    // same as any other change to the task.
    $activity['can_edit'] = can_edit_activity($activity);
    $activity['can_delete'] = can_delete_activity($activity);
    $activity['vacation_conflict'] = activity_vacation_conflict($activity);
    json_response(['ok' => true, 'activity' => $activity]);
}

// Live check used by the activity modal while creating/editing a task, before
// it's saved — lets an assignee/date change show the warning immediately
// rather than only after the next full page load. Shares the exact same
// overlap rule as activity_vacation_conflict()/bulk_activity_vacation_conflicts()
// via find_overlapping_vacation().
if ($method === 'GET' && $action === 'check_vacation_conflict') {
    $assigneeId = (int)($_GET['assignee_id'] ?? 0);
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    $conflict = ($assigneeId && $start !== '' && $end !== '')
        ? find_overlapping_vacation($assigneeId, substr($start, 0, 10), substr($end, 0, 10))
        : null;
    json_response(['ok' => true, 'conflict' => $conflict]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();
    foreach (['planned_start_at', 'target_completion_at', 'requested_at', 'interruption_started_at', 'interruption_ended_at'] as $dtField) {
        if (array_key_exists($dtField, $data)) {
            $data[$dtField] = normalize_dt($data[$dtField]);
        }
    }

    // ---- Planned activity / project task creation ----
    if ($action === 'create_planned') {
        $errors = validate_activity_input($data + ['activity_type' => 'planned']);
        if ($errors) json_error(implode(' ', $errors));
        $id = create_activity($data, 'planned', $user['id'], !empty($data['is_adhoc']));

        // Simple recurrence: create additional independent occurrences up to repeat_until.
        if (!empty($data['repeat_frequency']) && !empty($data['repeat_until']) && !empty($data['planned_start_at'])) {
            $stepDays = $data['repeat_frequency'] === 'weekly' ? 7 : 1;
            $cursor = new DateTime($data['planned_start_at']);
            $until = new DateTime($data['repeat_until']);
            $target = $data['target_completion_at'] ? new DateTime($data['target_completion_at']) : null;
            $guard = 0;
            while ($guard++ < 366) {
                $cursor->modify("+$stepDays day");
                if ($cursor > $until) break;
                $occData = $data;
                $occData['planned_start_at'] = $cursor->format('Y-m-d H:i:s');
                if ($target) {
                    $diff = $target->getTimestamp() - (new DateTime($data['planned_start_at']))->getTimestamp();
                    $occData['target_completion_at'] = date('Y-m-d H:i:s', $cursor->getTimestamp() + $diff);
                }
                create_activity($occData, 'planned', $user['id'], !empty($data['is_adhoc']));
            }
        }

        json_response(['ok' => true, 'activity' => get_activity($id)]);
    }

    // ---- Quick-add unplanned/ad-hoc activity (section 9) ----
    if ($action === 'quick_add') {
        $data['requester_id'] = $data['requester_id'] ?? current_person_id();
        $data['assignee_id'] = $data['assignee_id'] ?: current_person_id();
        $errors = validate_activity_input($data + ['activity_type' => 'unplanned']);
        if ($errors) json_error(implode(' ', $errors));
        if (empty($data['requested_at'])) $data['requested_at'] = now();

        $id = create_activity($data, 'unplanned', $user['id'], !empty($data['is_adhoc']));

        if (!empty($data['interrupted_activity_id'])) {
            record_interruption([
                'interrupting_activity_id' => $id,
                'interrupted_activity_id' => $data['interrupted_activity_id'],
                'started_at' => $data['interruption_started_at'] ?? now(),
                'ended_at' => $data['interruption_ended_at'] ?? null,
                'time_lost_minutes' => $data['time_lost_minutes'] ?? null,
                'was_resumed' => $data['was_resumed'] ?? null,
                'impact_on_target_date' => $data['impact_on_target_date'] ?? null,
                'notes' => $data['interruption_notes'] ?? null,
            ]);
        }

        json_response(['ok' => true, 'activity' => get_activity($id)]);
    }

    if ($action === 'update') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_edit_activity($activity)) deny();
        $errors = validate_activity_input($data);
        if ($errors) json_error(implode(' ', $errors));
        update_activity((int)$activity['id'], $data);
        json_response(['ok' => true, 'activity' => get_activity((int)$activity['id'])]);
    }

    if ($action === 'update_status') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_edit_activity($activity)) deny();
        try {
            update_activity_status((int)$activity['id'], $data['status'], isset($data['completion_pct']) ? (int)$data['completion_pct'] : null);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'activity' => get_activity((int)$activity['id'])]);
    }

    if ($action === 'update_progress') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_edit_activity($activity)) deny();
        update_activity_progress((int)$activity['id'], (int)$data['completion_pct']);
        json_response(['ok' => true, 'activity' => get_activity((int)$activity['id'])]);
    }

    if ($action === 'reclassify') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_reclassify_activity($activity)) deny('Only a project manager or administrator can reclassify a task.');
        try {
            reclassify_activity((int)$activity['id'], $data['new_type'], $data['reason'] ?? '', $user['id']);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'activity' => get_activity((int)$activity['id'])]);
    }

    if ($action === 'reorder') {
        reorder_activities($data['ordered_ids'] ?? []);
        json_response(['ok' => true]);
    }

    if ($action === 'reschedule') {
        // Used by calendar/My Day drag-and-drop.
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_edit_activity($activity)) deny();
        update_activity((int)$activity['id'], array_merge($activity, [
            'planned_start_at' => $data['planned_start_at'] ?? $activity['planned_start_at'],
            'target_completion_at' => $data['target_completion_at'] ?? $activity['target_completion_at'],
        ]));
        json_response(['ok' => true, 'activity' => get_activity((int)$activity['id'])]);
    }

    if ($action === 'copy_to_date') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        $newId = copy_activity_to_date((int)$activity['id'], $data['new_date'], $user['id']);
        json_response(['ok' => true, 'activity' => get_activity($newId)]);
    }

    if ($action === 'add_comment') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        try {
            add_activity_comment((int)$activity['id'], $user['id'], (string)($data['body'] ?? ''));
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'comments' => list_activity_comments((int)$activity['id'])]);
    }

    if ($action === 'edit_comment') {
        $comment = get_activity_comment((int)($data['id'] ?? 0));
        if (!$comment) json_error('Comment not found.', 404);
        if (!can_edit_comment($comment)) deny('You can only edit your own comments.');
        try {
            update_activity_comment((int)$comment['id'], (string)($data['body'] ?? ''));
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'comments' => list_activity_comments((int)$comment['activity_id'])]);
    }

    if ($action === 'set_tags') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        set_activity_tags((int)$activity['id'], is_array($data['tags'] ?? null) ? $data['tags'] : explode(',', (string)($data['tags'] ?? '')));
        json_response(['ok' => true, 'tags' => get_activity_tags((int)$activity['id'])]);
    }

    if ($action === 'add_dependency') {
        add_activity_dependency((int)$data['id'], (int)$data['depends_on_id']);
        json_response(['ok' => true, 'dependencies' => list_activity_dependencies((int)$data['id'])]);
    }

    if ($action === 'delete') {
        $activity = get_activity((int)($data['id'] ?? 0));
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_delete_activity($activity)) {
            deny('Only the assignee, the task\'s creator, an administrator, or the owning project manager can delete this task.');
        }
        delete_activity((int)$activity['id']);
        json_response(['ok' => true]);
    }

    // Shared by single-task and bulk clone/move: validates every source task and
    // the destination project up front, so the request either fully succeeds or
    // fully fails — no partial batches left half-applied.
    if ($action === 'clone' || $action === 'move') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($data['ids'] ?? [])))));
        if (!$ids) json_error('No tasks were selected.');

        $targetProjectId = (int)($data['project_id'] ?? 0);
        if (!$targetProjectId) json_error('Choose a destination project.');
        $targetProject = get_project($targetProjectId);
        if (!$targetProject) json_error('Destination project not found.', 404);
        if (!can_add_task_to_project($targetProject)) {
            deny('You do not have permission to add tasks to that project.');
        }

        $activities = [];
        foreach ($ids as $id) {
            $activity = get_activity($id);
            if (!$activity) json_error("Task #$id was not found.", 404);
            if (!can_edit_activity($activity)) {
                deny('You do not have permission to ' . ($action === 'clone' ? 'clone' : 'move') . ' one or more of the selected tasks.');
            }
            $activities[] = $activity;
        }

        $resultIds = [];
        foreach ($activities as $activity) {
            if ($action === 'clone') {
                $resultIds[] = clone_activity($activity, $targetProjectId, $user['id']);
            } else {
                move_activity_to_project((int)$activity['id'], $targetProjectId);
                $resultIds[] = (int)$activity['id'];
            }
        }

        json_response(['ok' => true, 'action' => $action, 'ids' => $resultIds]);
    }
}

json_error('Unknown action.', 404);

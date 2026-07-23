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
    json_response(['ok' => true, 'activities' => list_activities($filters)]);
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
    json_response(['ok' => true, 'activity' => $activity]);
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
        add_activity_comment((int)$activity['id'], $user['id'], trim($data['body'] ?? ''));
        json_response(['ok' => true, 'comments' => list_activity_comments((int)$activity['id'])]);
    }

    if ($action === 'edit_comment') {
        $comment = get_activity_comment((int)($data['id'] ?? 0));
        if (!$comment) json_error('Comment not found.', 404);
        if (!can_edit_comment($comment)) deny('You can only edit your own comments.');
        $body = trim($data['body'] ?? '');
        if ($body === '') json_error('Comment cannot be empty.');
        update_activity_comment((int)$comment['id'], $body);
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
}

json_error('Unknown action.', 404);

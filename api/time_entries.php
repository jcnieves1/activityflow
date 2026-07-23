<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    $activity = get_activity((int)($_GET['activity_id'] ?? 0));
    if (!$activity) json_error('Activity not found.', 404);
    if (!activity_is_visible($activity)) deny();
    json_response(['ok' => true, 'time_entries' => list_time_entries_for_activity((int)$activity['id'])]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $data = request_input();
    $action = $data['action'] ?? '';
    foreach (['started_at', 'ended_at'] as $dtField) {
        if (array_key_exists($dtField, $data)) {
            $data[$dtField] = normalize_dt($data[$dtField]);
        }
    }

    if ($action === 'manual') {
        $activityId = (int)($data['activity_id'] ?? 0);
        $activity = get_activity($activityId);
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_edit_activity($activity)) deny();
        if (empty($data['started_at'])) json_error('Start time is required.');
        $personId = current_person_id() ?? (int)$activity['assignee_id'];
        $result = manual_time_entry(
            $activityId, $personId, $data['started_at'], $data['ended_at'] ?: null,
            $data['duration_minutes'] !== '' ? (int)$data['duration_minutes'] : null, $data['notes'] ?? null
        );
        if (!$result['ok']) json_error($result['error']);
        json_response($result);
    }

    if ($action === 'update') {
        $entry = get_time_entry((int)($data['id'] ?? 0));
        if (!$entry) json_error('Time entry not found.', 404);
        $activity = get_activity((int)$entry['activity_id']);
        if (!$activity || !can_edit_activity($activity)) deny();
        $result = update_time_entry((int)$entry['id'], $data);
        if (!$result['ok']) json_error($result['error']);
        json_response($result);
    }
}

json_error('Unknown action.', 404);

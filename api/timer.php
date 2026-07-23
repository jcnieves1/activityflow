<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = require_login();

$personId = current_person_id();
if (!$personId) {
    json_error('Your account is not linked to a person record.', 400);
}

$data = request_input();
$action = $data['action'] ?? ($_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'active') {
    json_response(['ok' => true, 'timer' => active_timer_for($personId)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if ($action === 'start') {
        $activityId = (int)($data['activity_id'] ?? 0);
        $activity = get_activity($activityId);
        if (!$activity) json_error('Activity not found.', 404);
        if (!can_edit_activity($activity)) deny();
        $result = start_timer($activityId, $personId);
        if (!$result['ok']) json_error($result['error']);
        json_response($result);
    }

    if ($action === 'pause') {
        $result = pause_or_stop_timer($personId, false);
        if (!$result['ok']) json_error($result['error']);
        json_response($result);
    }

    if ($action === 'stop') {
        $result = pause_or_stop_timer($personId, true);
        if (!$result['ok']) json_error($result['error']);
        json_response($result);
    }
}

json_error('Unknown action.', 404);

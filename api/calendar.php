<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? 'events') === 'events') {
    $filters = [
        'assignee_id' => $_GET['assignee_id'] ?? '',
        'project_id' => $_GET['project_id'] ?? '',
        'activity_type' => $_GET['activity_type'] ?? '',
        'date_from' => isset($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : '',
        'date_to' => isset($_GET['end']) ? date('Y-m-d', strtotime($_GET['end'])) : '',
        'limit' => 1000,
    ];
    $activities = list_activities($filters);

    $events = array_map(function ($a) {
        $color = $a['project_color'] ?? ($a['activity_type'] === 'unplanned' ? '#f4a261' : '#4361ee');
        if ($a['priority'] === 'urgent') $color = '#e63946';
        if ($a['status'] === 'completed') $color = '#2a9d8f';
        if ($a['status'] === 'blocked') $color = '#6c757d';

        $start = $a['planned_start_at'] ?: $a['requested_at'];
        $end = $a['target_completion_at'] ?: null;
        if (!$end && $start && $a['estimated_minutes']) {
            $end = date('Y-m-d H:i:s', strtotime($start) + (int)$a['estimated_minutes'] * 60);
        }

        return [
            'id' => $a['id'],
            'title' => ($a['activity_type'] === 'unplanned' ? '⚡ ' : '') . $a['title'],
            'start' => $start ? str_replace(' ', 'T', $start) : null,
            'end' => $end ? str_replace(' ', 'T', $end) : null,
            'color' => $color,
            'extendedProps' => [
                'status' => $a['status'], 'type' => $a['activity_type'], 'assignee' => $a['assignee_name'],
                'project' => $a['project_name'], 'requester' => $a['requester_name'], 'priority' => $a['priority'],
            ],
        ];
    }, array_filter($activities, fn($a) => $a['planned_start_at'] || $a['requested_at']));

    json_response(array_values($events));
}

json_error('Unknown action.', 404);

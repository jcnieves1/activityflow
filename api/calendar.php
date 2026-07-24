<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? 'events') === 'events') {
    // assignee_id[]/project_id[] (repeated query params from the calendar's
    // multi-select filters) take priority over the older single-value
    // assignee_id/project_id, which are kept as a fallback for any direct
    // API caller still passing one plain id.
    $assigneeIds = array_filter((array)($_GET['assignee_id'] ?? []), fn($v) => $v !== '');
    $projectIds = array_filter((array)($_GET['project_id'] ?? []), fn($v) => $v !== '');

    $filters = [
        'activity_type' => $_GET['activity_type'] ?? '',
        'date_from' => isset($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : '',
        'date_to' => isset($_GET['end']) ? date('Y-m-d', strtotime($_GET['end'])) : '',
        'limit' => 1000,
    ];
    if ($assigneeIds) {
        $filters['assignee_id_in'] = $assigneeIds;
    }
    if ($projectIds) {
        $filters['project_id_in'] = $projectIds;
    }
    $activities = list_activities($filters);

    $events = array_map(function ($a) {
        // Color reflects the event's planned/unplanned + status/priority
        // state — exactly what the calendar's legend documents (Planned,
        // Unplanned, Urgent, Completed, Blocked) — the same precedence
        // project_board.php uses for its task cards. This used to fall back
        // to the task's *project* color when one was set, which silently
        // overrode the legend for the majority of tasks (anything attached
        // to a project) with whatever arbitrary color that project's owner
        // picked, making the on-screen legend meaningless for those events.
        $color = $a['activity_type'] === 'unplanned' ? '#f4a261' : '#4361ee';
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

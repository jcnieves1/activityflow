<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

if (($_GET['action'] ?? 'day') !== 'day') {
    json_error('Unknown action.', 404);
}

$employeeId = (int)($_GET['employee_id'] ?? current_person_id());
$date = $_GET['date'] ?? date('Y-m-d');
$projectId = $_GET['project_id'] ?? '';
$requesterId = $_GET['requester_id'] ?? '';

if (!$employeeId) {
    json_error('No employee selected.');
}

$pdo = db();

// Planned schedule for the day (the original plan track).
$plannedSql = 'SELECT a.id, a.title, a.status, a.activity_type, a.planned_start_at, a.target_completion_at,
                      a.priority, pr.name AS project_name, req.full_name AS requester_name
               FROM activities a
               LEFT JOIN projects pr ON pr.id = a.project_id
               LEFT JOIN people req ON req.id = a.requester_id
               WHERE a.assignee_id = ? AND a.is_active = 1 AND DATE(a.planned_start_at) = ?';
$params = [$employeeId, $date];
if ($projectId !== '') { $plannedSql .= ' AND a.project_id = ?'; $params[] = $projectId; }
if ($requesterId !== '') { $plannedSql .= ' AND a.requester_id = ?'; $params[] = $requesterId; }
$stmt = $pdo->prepare($plannedSql);
$stmt->execute($params);
$planned = $stmt->fetchAll();

// Actual execution periods (time entries) for the day.
$stmt = $pdo->prepare(
    'SELECT te.id, te.activity_id, te.started_at, te.ended_at, te.duration_minutes, te.is_manual,
            a.title, a.activity_type, a.status, pr.name AS project_name
     FROM time_entries te
     JOIN activities a ON a.id = te.activity_id
     LEFT JOIN projects pr ON pr.id = a.project_id
     WHERE te.person_id = ? AND DATE(te.started_at) = ?
     ORDER BY te.started_at'
);
$stmt->execute([$employeeId, $date]);
$actual = $stmt->fetchAll();

// Unplanned insertions requested that day (regardless of when the work happened).
$stmt = $pdo->prepare(
    'SELECT a.id, a.title, a.status, a.priority, a.requested_at, a.actual_start_at, a.actual_completion_at,
            req.full_name AS requester_name, pr.name AS project_name, a.request_channel, a.interruption_reason
     FROM activities a
     LEFT JOIN people req ON req.id = a.requester_id
     LEFT JOIN projects pr ON pr.id = a.project_id
     WHERE a.assignee_id = ? AND a.activity_type = "unplanned" AND DATE(a.requested_at) = ?
     ORDER BY a.requested_at'
);
$stmt->execute([$employeeId, $date]);
$unplanned = $stmt->fetchAll();

// Interruptions tied to those unplanned insertions.
$interruptions = [];
if ($unplanned) {
    $ids = array_column($unplanned, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT i.*, a.title AS interrupted_title FROM interruptions i
                            LEFT JOIN activities a ON a.id = i.interrupted_activity_id
                            WHERE i.interrupting_activity_id IN ($in)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $interruptions[$row['interrupting_activity_id']] = $row;
    }
}
foreach ($unplanned as &$u) {
    $u['interruption'] = $interruptions[$u['id']] ?? null;
}
unset($u);

// Tasks moved to another day (schedule history where date changed away from this one).
$stmt = $pdo->prepare(
    'SELECT h.*, a.title FROM activity_schedule_history h
     JOIN activities a ON a.id = h.activity_id
     WHERE a.assignee_id = ? AND DATE(h.old_planned_start_at) = ? AND DATE(h.new_planned_start_at) != ?'
);
$stmt->execute([$employeeId, $date, $date]);
$moved = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT full_name FROM people WHERE id = ?');
$stmt->execute([$employeeId]);
$employeeName = $stmt->fetchColumn() ?: 'Unknown';

json_response([
    'ok' => true,
    'date' => $date,
    'employee' => ['id' => $employeeId, 'name' => $employeeName],
    'planned' => $planned,
    'actual' => $actual,
    'unplanned' => $unplanned,
    'moved' => $moved,
]);

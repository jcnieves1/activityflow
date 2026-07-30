<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'data';

if ($action === 'data') {
    $filters = [
        'release_id_in' => array_map('intval', array_filter((array)($_GET['release_id'] ?? []), fn($v) => $v !== '')),
        'project_id_in' => array_map('intval', array_filter((array)($_GET['project_id'] ?? []), fn($v) => $v !== '')),
        'status_in' => array_values(array_filter((array)($_GET['status'] ?? []), fn($v) => $v !== '')),
        'assignee_id_in' => array_map('intval', array_filter((array)($_GET['assignee_id'] ?? []), fn($v) => $v !== '')),
    ];
    json_response(array_merge(['ok' => true], mindmap_data($filters)));
}

json_error('Unknown action.', 400);

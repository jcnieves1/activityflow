<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if (!can_view_workload()) {
    deny(t('workload.no_access'));
}

$action = $_GET['action'] ?? 'summary';

if ($action === 'summary') {
    $personIds = array_filter((array)($_GET['person_id'] ?? []), fn($v) => $v !== '');
    $statuses = array_filter((array)($_GET['status'] ?? []), fn($v) => $v !== '');
    $filters = [
        'person_id_in' => array_map('intval', $personIds),
        'status_in' => array_values($statuses),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'order' => $_GET['order'] ?? 'asc',
    ];
    json_response(['ok' => true, 'results' => workload_summary($filters)]);
}

json_error('Unknown action.', 400);

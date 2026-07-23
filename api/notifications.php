<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');

if ($method === 'GET' && $action === 'list') {
    $limit = (int)($_GET['limit'] ?? 30);
    json_response(['ok' => true, 'notifications' => list_notifications($user['id'], $limit), 'unread' => unread_notification_count($user['id'])]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'mark_read') {
        mark_notification_read($user['id'], (int)$data['id']);
        json_response(['ok' => true]);
    }
    if ($action === 'mark_all_read') {
        mark_all_notifications_read($user['id']);
        json_response(['ok' => true]);
    }
}

json_error('Unknown action.', 404);

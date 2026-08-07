<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');

if ($method === 'GET' && $action === 'list') {
    $online = list_online_users();
    $users = array_map(static fn(array $u) => [
        'id'        => (int)$u['id'],
        'full_name' => $u['full_name'],
        'is_self'   => (int)$u['id'] === (int)$user['id'],
    ], $online);
    json_response(['ok' => true, 'users' => $users, 'count' => count($users)]);
}

json_error('Unknown action.', 404);

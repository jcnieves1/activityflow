<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
require_role([ROLE_ADMIN]);

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : (request_input()['action'] ?? '');
$user = current_user();

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'approve') {
        $userId = (int)($data['user_id'] ?? 0);
        try {
            approve_account($userId, isset($data['reason']) ? (string)$data['reason'] : null, (int)$user['id']);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response([
            'ok' => true,
            'pending' => list_pending_accounts(),
            'history' => list_account_approval_history(),
        ]);
    }

    if ($action === 'reject') {
        $userId = (int)($data['user_id'] ?? 0);
        try {
            reject_account($userId, (string)($data['reason'] ?? ''), (int)$user['id']);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response([
            'ok' => true,
            'pending' => list_pending_accounts(),
            'history' => list_account_approval_history(),
        ]);
    }
}

json_error('Unknown action.', 404);

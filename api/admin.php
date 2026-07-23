<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
require_role([ROLE_ADMIN]);

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : (request_input()['action'] ?? '');

if ($method === 'GET' && $action === 'users') {
    $stmt = $pdo->query(
        'SELECT u.id, u.full_name, u.email, u.status, u.last_login_at, p.id AS person_id,
                GROUP_CONCAT(r.name) AS roles
         FROM users u
         LEFT JOIN people p ON p.user_id = u.id
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         GROUP BY u.id ORDER BY u.full_name'
    );
    json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
}

if ($method === 'GET' && $action === 'roles') {
    json_response(['ok' => true, 'roles' => $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll()]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'set_status') {
        $id = (int)$data['id'];
        $status = $data['status'];
        if (!in_array($status, ['active', 'inactive', 'locked'], true)) json_error('Invalid status.');
        $pdo->prepare('UPDATE users SET status = ?, failed_login_count = 0 WHERE id = ?')->execute([$status, $id]);
        audit_log('user', $id, 'status_changed', null, ['status' => $status]);
        json_response(['ok' => true]);
    }

    if ($action === 'set_roles') {
        $id = (int)$data['id'];
        $roleIds = array_map('intval', (array)($data['role_ids'] ?? []));
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
        foreach ($roleIds as $rid) { $ins->execute([$id, $rid]); }
        $pdo->commit();
        audit_log('user', $id, 'roles_changed', null, ['role_ids' => $roleIds]);
        json_response(['ok' => true]);
    }

    if ($action === 'category_save') {
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE activity_categories SET name=?, description=?, is_active=? WHERE id=?')
                ->execute([$data['name'], $data['description'] ?? null, !empty($data['is_active']) ? 1 : 0, $id]);
        } else {
            $pdo->prepare('INSERT INTO activity_categories (name, description, is_active) VALUES (?,?,1)')
                ->execute([$data['name'], $data['description'] ?? null]);
            $id = (int)$pdo->lastInsertId();
        }
        audit_log('activity_category', $id, 'saved');
        json_response(['ok' => true]);
    }

    if ($action === 'department_save') {
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE departments SET name=?, is_active=? WHERE id=?')->execute([$data['name'], !empty($data['is_active']) ? 1 : 0, $id]);
        } else {
            $pdo->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$data['name']]);
            $id = (int)$pdo->lastInsertId();
        }
        audit_log('department', $id, 'saved');
        json_response(['ok' => true]);
    }
}

json_error('Unknown action.', 404);

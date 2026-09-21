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
    // Accounts still awaiting a decision ('pending_approval') or declined
    // ('rejected') are deliberately excluded here — they're managed
    // exclusively through admin/account_approvals.php, which requires a
    // reason and records it for audit. Surfacing them on this generic page
    // too would let the plain Activate button flip one straight to 'active'
    // with no reason captured, bypassing that flow entirely. A rejected
    // account that's later approved becomes 'active' and appears here
    // normally, same as any other user.
    $stmt = $pdo->query(
        "SELECT u.id, u.full_name, u.email, u.status, u.last_login_at, p.id AS person_id,
                GROUP_CONCAT(r.name) AS roles
         FROM users u
         LEFT JOIN people p ON p.user_id = u.id
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.status NOT IN ('pending_approval', 'rejected')
         GROUP BY u.id ORDER BY u.full_name"
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
        // An account still awaiting a decision, or already declined, must go
        // through admin/account_approvals.php's approve/reject actions
        // instead — those require (and record) a reason, and log to
        // account_approval_actions for the Decision history list. Blocking
        // it here too (not just hiding the button in the UI) keeps that the
        // only path, so every activation of one of these accounts is always
        // accounted for there.
        $currentStmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $currentStmt->execute([$id]);
        $currentStatus = $currentStmt->fetchColumn();
        if (in_array($currentStatus, ['pending_approval', 'rejected'], true)) {
            json_error('This account is awaiting approval — use Account Approvals to approve or reject it.');
        }
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

    if ($action === 'status_save') {
        $id = (int)($data['id'] ?? 0);
        $label = trim((string)($data['label'] ?? ''));
        try {
            $status = $id ? update_task_status($id, $label) : create_task_status($label);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'status' => $status]);
    }

    if ($action === 'status_delete') {
        $id = (int)($data['id'] ?? 0);
        $replacementSlug = isset($data['replacement_slug']) && $data['replacement_slug'] !== ''
            ? (string)$data['replacement_slug'] : null;
        try {
            $result = delete_task_status($id, $replacementSlug);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true] + $result);
    }

    if ($action === 'request_channel_save') {
        $id = (int)($data['id'] ?? 0);
        $label = trim((string)($data['label'] ?? ''));
        try {
            $channel = $id ? update_request_channel($id, $label) : create_request_channel($label);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'channel' => $channel]);
    }

    if ($action === 'request_channel_delete') {
        $id = (int)($data['id'] ?? 0);
        $replacementSlug = isset($data['replacement_slug']) && $data['replacement_slug'] !== ''
            ? (string)$data['replacement_slug'] : null;
        try {
            $result = delete_request_channel($id, $replacementSlug);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true] + $result);
    }

    if ($action === 'release_save') {
        $id = (int)($data['id'] ?? 0);
        try {
            if ($id) {
                update_release($id, $data);
            } else {
                $id = create_release($data, current_user()['id'] ?? null);
            }
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'release' => get_release($id)]);
    }

    if ($action === 'release_delete') {
        $id = (int)($data['id'] ?? 0);
        if (!delete_release($id)) {
            json_error('Release not found.', 404);
        }
        json_response(['ok' => true]);
    }

    if ($action === 'release_phase_save') {
        $id = (int)($data['id'] ?? 0);
        $releaseId = (int)($data['release_id'] ?? 0);
        try {
            if ($id) {
                update_release_phase($id, $data);
                $phase = get_release_phase($id);
            } else {
                $newId = create_release_phase($releaseId, $data);
                $phase = get_release_phase($newId);
            }
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'phase' => $phase]);
    }

    if ($action === 'release_phase_delete') {
        $id = (int)($data['id'] ?? 0);
        try {
            delete_release_phase($id);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true]);
    }

    if ($action === 'release_associate_project') {
        $releaseId = (int)($data['release_id'] ?? 0);
        $projectId = (int)($data['project_id'] ?? 0);
        try {
            associate_project_to_release($releaseId, $projectId);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true]);
    }

    if ($action === 'release_move_project') {
        $releaseId = (int)($data['release_id'] ?? 0);
        $projectId = (int)($data['project_id'] ?? 0);
        try {
            move_project_to_release($projectId, $releaseId);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true]);
    }

    if ($action === 'release_disassociate_project') {
        $projectId = (int)($data['project_id'] ?? 0);
        try {
            disassociate_project_from_release($projectId);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true]);
    }

    if ($action === 'release_phase_template_save') {
        $id = (int)($data['id'] ?? 0);
        $name = (string)($data['name'] ?? '');
        try {
            $template = $id ? update_release_phase_template($id, $name) : create_release_phase_template($name);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'template' => $template]);
    }

    if ($action === 'release_phase_template_delete') {
        $id = (int)($data['id'] ?? 0);
        try {
            delete_release_phase_template($id);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true]);
    }

    if ($action === 'release_phase_template_move') {
        $id = (int)($data['id'] ?? 0);
        $direction = (string)($data['direction'] ?? '');
        if (!in_array($direction, ['up', 'down'], true)) {
            json_error('Invalid direction.');
        }
        try {
            move_release_phase_template($id, $direction);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'templates' => list_release_phase_templates()]);
    }
}

json_error('Unknown action.', 404);

<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');

if ($method === 'GET' && $action === 'list') {
    json_response(['ok' => true, 'people' => list_people([
        'search' => $_GET['search'] ?? '',
        'is_active' => $_GET['is_active'] ?? '',
        'department_id' => $_GET['department_id'] ?? '',
    ])]);
}

if ($method === 'GET' && $action === 'search') {
    // Lightweight typeahead used by activity/task forms.
    $people = list_people(['search' => $_GET['q'] ?? '', 'is_active' => 1]);
    json_response(['ok' => true, 'people' => array_map(fn($p) => [
        'id' => (int)$p['id'], 'full_name' => $p['full_name'], 'job_title' => $p['job_title'], 'email' => $p['email'],
    ], $people)]);
}

if ($method === 'GET' && $action === 'get') {
    $person = get_person((int)($_GET['id'] ?? 0));
    if (!$person) {
        json_error('Person not found.', 404);
    }
    json_response(['ok' => true, 'person' => $person]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'check_duplicate') {
        $matches = find_similar_people(trim((string)($data['full_name'] ?? '')), $data['email'] ?? null);
        json_response(['ok' => true, 'matches' => $matches]);
    }

    if ($action === 'create') {
        if (!is_admin() && !user_has_role(ROLE_PM) && !user_has_role(ROLE_EMPLOYEE)) {
            deny('You do not have permission to add people.');
        }
        $missing = missing_fields($data, ['full_name']);
        if ($missing) {
            json_error('Full name is required.');
        }
        $id = create_person($data);
        json_response(['ok' => true, 'person' => get_person($id)]);
    }

    if ($action === 'update') {
        require_role([ROLE_ADMIN, ROLE_PM]);
        $id = (int)($data['id'] ?? 0);
        if (!get_person($id)) {
            json_error('Person not found.', 404);
        }
        update_person($id, $data);
        json_response(['ok' => true, 'person' => get_person($id)]);
    }

    if ($action === 'set_active') {
        require_role([ROLE_ADMIN]);
        $id = (int)($data['id'] ?? 0);
        set_person_active($id, !empty($data['active']));
        json_response(['ok' => true]);
    }
}

json_error('Unknown action.', 404);

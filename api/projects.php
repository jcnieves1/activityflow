<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');

if ($method === 'GET' && $action === 'list') {
    // Defense in depth: this generic endpoint is reachable directly by URL,
    // so a restricted role must be scoped here too, not just on the pages
    // that happen to filter their own list_projects() calls before showing it.
    json_response(['ok' => true, 'projects' => filter_visible_projects(list_projects([
        'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '',
        'owner_id' => $_GET['owner_id'] ?? '', 'is_archived' => $_GET['is_archived'] ?? '',
    ]))]);
}

if ($method === 'GET' && $action === 'get') {
    $project = get_project((int)($_GET['id'] ?? 0));
    if (!$project) json_error('Project not found.', 404);
    if (!can_view_project($project)) deny();
    $project['progress_default'] = calculate_project_progress((int)$project['id'], 'duration_weighted');
    $project['progress_simple'] = calculate_project_progress((int)$project['id'], 'simple_count');
    $project['members'] = list_project_members((int)$project['id']);
    json_response(['ok' => true, 'project' => $project]);
}

if ($method === 'GET' && $action === 'progress') {
    $id = (int)($_GET['id'] ?? 0);
    $method2 = $_GET['method'] ?? 'duration_weighted';
    json_response(['ok' => true, 'progress' => calculate_project_progress($id, $method2)]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'create') {
        require_role([ROLE_ADMIN, ROLE_PM]);
        $missing = missing_fields($data, ['name', 'code', 'owner_id']);
        if ($missing) json_error('Name, code, and owner are required.');
        if (get_project_by_code($data['code'])) json_error('A project with that code already exists.');
        $id = create_project($data, current_user()['id']);
        // Owner is already a project_manager member (added inside create_project()).
        // Any additionally-selected people from the New Project dialog's member
        // picker are added as plain contributors.
        $memberIds = array_map('intval', (array)($data['member_ids'] ?? []));
        foreach (array_unique($memberIds) as $personId) {
            if ($personId !== (int)$data['owner_id']) {
                add_project_member($id, $personId, 'contributor');
            }
        }
        json_response(['ok' => true, 'project' => get_project($id)]);
    }

    if ($action === 'update') {
        $project = get_project((int)($data['id'] ?? 0));
        if (!$project) json_error('Project not found.', 404);
        if (!can_manage_project($project)) deny('Only the project owner or an administrator can edit this project.');
        $missing = missing_fields($data, ['name', 'code', 'owner_id']);
        if ($missing) json_error('Name, code, and owner are required.');
        if (strcasecmp($data['code'], $project['code']) !== 0) {
            $codeOwner = get_project_by_code($data['code']);
            if ($codeOwner && (int)$codeOwner['id'] !== (int)$project['id']) {
                json_error('Another project already uses that code.');
            }
        }
        update_project((int)$project['id'], $data);
        // Reconcile membership against the Edit Project dialog's member picker, if
        // submitted (it always is from the UI; array_key_exists guards direct API
        // callers that omit it entirely from touching membership at all).
        if (array_key_exists('member_ids', $data)) {
            $memberIds = array_map('intval', (array)$data['member_ids']);
            sync_project_members((int)$project['id'], $memberIds, (int)$data['owner_id']);
        }
        json_response(['ok' => true, 'project' => get_project((int)$project['id'])]);
    }

    if ($action === 'add_member') {
        $project = get_project((int)($data['project_id'] ?? 0));
        if (!$project) json_error('Project not found.', 404);
        if (!can_manage_project($project)) deny('Only the project owner or an administrator can manage members.');
        add_project_member((int)$project['id'], (int)$data['person_id'], $data['project_role'] ?? 'contributor');
        json_response(['ok' => true, 'members' => list_project_members((int)$project['id'])]);
    }

    if ($action === 'remove_member') {
        $project = get_project((int)($data['project_id'] ?? 0));
        if (!$project) json_error('Project not found.', 404);
        if (!can_manage_project($project)) deny('Only the project owner or an administrator can manage members.');
        remove_project_member((int)$project['id'], (int)$data['person_id']);
        json_response(['ok' => true, 'members' => list_project_members((int)$project['id'])]);
    }

    if ($action === 'delete') {
        $project = get_project((int)($data['id'] ?? 0));
        if (!$project) json_error('Project not found.', 404);
        if (!can_manage_project($project)) deny('Only the project owner or an administrator can delete this project.');
        // Require the exact project name as a deliberate, server-enforced confirmation
        // step — this is a permanent, cascading delete (tasks, comments, time entries,
        // members, etc.) with no undo, so a stray/automated request can't trigger it.
        $confirmName = trim((string)($data['confirm_name'] ?? ''));
        if ($confirmName === '' || strcasecmp($confirmName, $project['name']) !== 0) {
            json_error('Type the project name exactly to confirm deletion.');
        }
        delete_project((int)$project['id']);
        json_response(['ok' => true]);
    }
}

json_error('Unknown action.', 404);

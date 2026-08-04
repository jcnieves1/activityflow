<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');
$user = current_user();

// Listing/reading templates is open to any logged-in user — needed so
// anyone who can already add tasks to a project (see can_add_task_to_project())
// can pick a template to apply there, even though only admins/PMs may
// create, edit, or delete templates themselves.
if ($method === 'GET' && $action === 'list') {
    json_response(['ok' => true, 'templates' => list_task_templates()]);
}

if ($method === 'GET' && $action === 'get') {
    $template = get_task_template((int)($_GET['id'] ?? 0));
    if (!$template) json_error('Template not found.', 404);
    $template['items'] = list_task_template_items((int)$template['id']);
    json_response(['ok' => true, 'template' => $template]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'create') {
        if (!can_manage_task_templates()) deny('Only administrators and project managers can create task templates.');
        try {
            $id = create_task_template($data, $user['id']);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'template' => get_task_template($id)]);
    }

    if ($action === 'update') {
        if (!can_manage_task_templates()) deny('Only administrators and project managers can edit task templates.');
        $template = get_task_template((int)($data['id'] ?? 0));
        if (!$template) json_error('Template not found.', 404);
        try {
            update_task_template((int)$template['id'], $data);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'template' => get_task_template((int)$template['id'])]);
    }

    if ($action === 'delete') {
        if (!can_manage_task_templates()) deny('Only administrators and project managers can delete task templates.');
        $template = get_task_template((int)($data['id'] ?? 0));
        if (!$template) json_error('Template not found.', 404);
        delete_task_template((int)$template['id']);
        json_response(['ok' => true]);
    }

    if ($action === 'item_save') {
        if (!can_manage_task_templates()) deny('Only administrators and project managers can edit task templates.');
        try {
            $itemId = save_task_template_item($data);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true, 'item' => get_task_template_item($itemId)]);
    }

    if ($action === 'item_delete') {
        if (!can_manage_task_templates()) deny('Only administrators and project managers can edit task templates.');
        $item = get_task_template_item((int)($data['id'] ?? 0));
        if (!$item) json_error('Template task not found.', 404);
        delete_task_template_item((int)$item['id']);
        json_response(['ok' => true]);
    }

    if ($action === 'item_move') {
        if (!can_manage_task_templates()) deny('Only administrators and project managers can edit task templates.');
        $direction = ($data['direction'] ?? '') === 'up' ? 'up' : 'down';
        try {
            move_task_template_item((int)($data['id'] ?? 0), $direction);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 404);
        }
        json_response(['ok' => true]);
    }

    // Applying a template to a project is deliberately broader than managing
    // the template library — anyone who can already add tasks to that
    // project (admin, PM, or a member of it — see can_add_task_to_project())
    // may apply one of the shared templates there.
    if ($action === 'apply') {
        $project = get_project((int)($data['project_id'] ?? 0));
        if (!$project) json_error('Project not found.', 404);
        if (!can_add_task_to_project($project)) {
            deny('You do not have permission to add tasks to this project.');
        }
        $template = get_task_template((int)($data['template_id'] ?? 0));
        if (!$template) json_error('Template not found.', 404);
        $itemIds = array_map('intval', (array)($data['item_ids'] ?? []));
        if (!$itemIds) json_error('Choose at least one task to add.');
        $personId = current_person_id();
        if (!$personId) json_error('Your account is not linked to a person record, so it cannot be recorded as the requester.');
        $createdIds = apply_task_template_to_project(
            (int)$template['id'], (int)$project['id'], $itemIds, $personId, $user['id']
        );
        json_response(['ok' => true, 'created_count' => count($createdIds), 'activity_ids' => $createdIds]);
    }
}

json_error('Unknown action.', 404);

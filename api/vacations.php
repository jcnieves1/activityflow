<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : (request_input()['action'] ?? '');
$user = current_user();

/** Notes are only meaningful to the person who took the time off and to admins — strip them out of any row nobody else should read the free-text reason for. */
function scrub_vacation_notes_for_viewer(array $vacation): array
{
    if (!can_manage_vacation($vacation)) {
        $vacation['notes'] = null;
    }
    return $vacation;
}

if ($method === 'GET' && $action === 'list') {
    $personIds = array_filter((array)($_GET['person_id'] ?? []), fn($v) => $v !== '');
    $filters = [
        'date_from' => $_GET['start'] ?? '',
        'date_to' => $_GET['end'] ?? '',
    ];
    if ($personIds) {
        $filters['person_id_in'] = $personIds;
    }
    $vacations = array_map(function ($v) {
        $v['can_manage'] = can_manage_vacation($v);
        return scrub_vacation_notes_for_viewer($v);
    }, list_vacations($filters));
    json_response(['ok' => true, 'vacations' => $vacations]);
}

if ($method === 'GET' && $action === 'conflicts') {
    $personIds = array_filter((array)($_GET['person_id'] ?? []), fn($v) => $v !== '');
    $filters = $personIds ? ['person_id_in' => $personIds] : [];
    json_response(['ok' => true, 'conflicts' => list_vacation_task_conflicts($filters)]);
}

if ($method === 'GET' && $action === 'get') {
    $vacation = get_vacation((int)($_GET['id'] ?? 0));
    if (!$vacation) json_error('Vacation entry not found.', 404);
    if (!can_manage_vacation($vacation)) {
        $vacation = scrub_vacation_notes_for_viewer($vacation);
    }
    $vacation['can_manage'] = can_manage_vacation($vacation);
    json_response(['ok' => true, 'vacation' => $vacation]);
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'create') {
        $personId = (int)($data['person_id'] ?? 0);
        // Anyone can log their own time off; only an administrator may log it
        // on behalf of someone else (e.g. entering it for a person who can't).
        if (!is_admin() && $personId !== (int)current_person_id()) {
            deny('You can only submit vacation time for yourself.');
        }
        try {
            $id = create_vacation($data, $user['id']);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'vacation' => get_vacation($id)]);
    }

    if ($action === 'update') {
        $vacation = get_vacation((int)($data['id'] ?? 0));
        if (!$vacation) json_error('Vacation entry not found.', 404);
        if (!can_manage_vacation($vacation)) {
            deny('Only that person or an administrator can edit this vacation entry.');
        }
        try {
            update_vacation((int)$vacation['id'], $data);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }
        json_response(['ok' => true, 'vacation' => get_vacation((int)$vacation['id'])]);
    }

    if ($action === 'delete') {
        $vacation = get_vacation((int)($data['id'] ?? 0));
        if (!$vacation) json_error('Vacation entry not found.', 404);
        if (!can_manage_vacation($vacation)) {
            deny('Only that person or an administrator can delete this vacation entry.');
        }
        delete_vacation((int)$vacation['id']);
        json_response(['ok' => true]);
    }
}

json_error('Unknown action.', 404);

<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : (request_input()['action'] ?? '');
$user = current_user();

/**
 * Loads the parent project or task for a polymorphic attachment request, or
 * fails the request outright (404 for an unknown entity, 400 for a bogus
 * entity_type) — every action below needs this before it can check
 * permissions, since "can view/manage this attachment" is really "can
 * view/manage its parent project/task".
 */
function attachment_load_entity(string $entityType, int $entityId): array
{
    if ($entityType === 'project') {
        $entity = get_project($entityId);
        if (!$entity) json_error('Project not found.', 404);
        return $entity;
    }
    if ($entityType === 'activity') {
        $entity = get_activity($entityId);
        if (!$entity) json_error('Task not found.', 404);
        return $entity;
    }
    json_error('Invalid entity type.', 400);
}

function attachment_can_view(string $entityType, array $entity): bool
{
    return $entityType === 'project' ? can_view_project($entity) : activity_is_visible($entity);
}

/** Upload/delete are both gated on the same "can manage this project/task" permission — uploading a file is a write to the entity, same as editing any other field. */
function attachment_can_manage(string $entityType, array $entity): bool
{
    return $entityType === 'project' ? can_manage_project($entity) : can_edit_activity($entity);
}

if ($method === 'GET' && $action === 'list') {
    $entityType = (string)($_GET['entity_type'] ?? '');
    $entityId = (int)($_GET['entity_id'] ?? 0);
    $entity = attachment_load_entity($entityType, $entityId);
    if (!attachment_can_view($entityType, $entity)) deny();
    json_response(['ok' => true, 'attachments' => list_attachments($entityType, $entityId)]);
}

// Not scoped to JSON: this is a plain browser navigation (an <a href> or
// window.location), so it authenticates via the session cookie alone, the
// same as any other GET page — no CSRF token is needed for a read-only
// download, and none could be attached to a plain link anyway.
if ($method === 'GET' && $action === 'download') {
    $attachment = get_attachment((int)($_GET['id'] ?? 0));
    if (!$attachment) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(404);
        exit('File not found.');
    }
    $entity = attachment_load_entity($attachment['entity_type'], (int)$attachment['entity_id']);
    if (!attachment_can_view($attachment['entity_type'], $entity)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(403);
        exit('You do not have access to this file.');
    }
    stream_attachment_download($attachment); // exits internally
}

if ($method === 'POST') {
    csrf_require();
    $data = request_input();

    if ($action === 'upload') {
        $entityType = (string)($data['entity_type'] ?? '');
        $entityId = (int)($data['entity_id'] ?? 0);
        $entity = attachment_load_entity($entityType, $entityId);
        if (!attachment_can_manage($entityType, $entity)) {
            deny('You do not have permission to upload files here.');
        }
        if (empty($_FILES['files'])) {
            json_error('No files were received.');
        }
        $files = normalize_uploaded_files_array($_FILES['files']);
        if (!$files) {
            json_error('No files were received.');
        }
        $uploadedCount = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                process_attachment_upload($file, $entityType, $entityId, $user['id'] ?? null);
                $uploadedCount++;
            } catch (InvalidArgumentException $e) {
                $label = trim((string)($file['name'] ?? '')) ?: 'file';
                $errors[] = "$label: " . $e->getMessage();
            }
        }
        json_response([
            'ok' => true,
            'attachments' => list_attachments($entityType, $entityId),
            'uploaded_count' => $uploadedCount,
            'errors' => $errors,
        ]);
    }

    if ($action === 'delete') {
        $attachment = get_attachment((int)($data['id'] ?? 0));
        if (!$attachment) json_error('File not found.', 404);
        $entity = attachment_load_entity($attachment['entity_type'], (int)$attachment['entity_id']);
        if (!attachment_can_manage($attachment['entity_type'], $entity)) {
            deny('You do not have permission to delete this file.');
        }
        delete_attachment((int)$attachment['id']);
        json_response(['ok' => true, 'attachments' => list_attachments($attachment['entity_type'], (int)$attachment['entity_id'])]);
    }
}

json_error('Unknown action.', 404);

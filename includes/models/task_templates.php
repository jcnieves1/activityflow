<?php
declare(strict_types=1);

/**
 * Task Templates: admin/PM-managed reusable sets of predefined tasks (see
 * task_templates + task_template_items in schema.sql) that can be applied
 * to any project so repetitive/recurring setup tasks — the same handful of
 * tasks every new project needs, or a recurring checklist — don't have to
 * be re-typed by hand every time. Managing the template library itself is
 * restricted to admins/PMs (see can_manage_task_templates() in
 * includes/permissions.php), but *applying* an existing template to a
 * project follows the same, broader can_add_task_to_project() rule already
 * used for cloning/moving tasks into a project (admin/PM, or any member of
 * that project) — see apply_task_template_to_project().
 */

function list_task_templates(): array
{
    return db()->query(
        'SELECT tt.*, u.full_name AS created_by_name,
                (SELECT COUNT(*) FROM task_template_items ti WHERE ti.template_id = tt.id) AS item_count
         FROM task_templates tt
         LEFT JOIN users u ON u.id = tt.created_by
         ORDER BY tt.name'
    )->fetchAll();
}

function get_task_template(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT tt.*, u.full_name AS created_by_name FROM task_templates tt
         LEFT JOIN users u ON u.id = tt.created_by WHERE tt.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_task_template_items(int $templateId): array
{
    $stmt = db()->prepare(
        'SELECT ti.*, c.name AS category_name FROM task_template_items ti
         LEFT JOIN activity_categories c ON c.id = ti.category_id
         WHERE ti.template_id = ? ORDER BY ti.sort_order, ti.id'
    );
    $stmt->execute([$templateId]);
    return $stmt->fetchAll();
}

function get_task_template_item(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM task_template_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_task_template(array $data, int $createdByUserId): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Template name is required.');
    }
    db()->prepare('INSERT INTO task_templates (name, description, created_by) VALUES (?,?,?)')
        ->execute([$name, trim((string)($data['description'] ?? '')) ?: null, $createdByUserId]);
    $id = (int)db()->lastInsertId();
    audit_log('task_template', $id, 'created', null, ['name' => $name]);
    return $id;
}

function update_task_template(int $id, array $data): void
{
    $before = get_task_template($id);
    if (!$before) {
        throw new RuntimeException('Template not found.');
    }
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Template name is required.');
    }
    db()->prepare('UPDATE task_templates SET name=?, description=? WHERE id=?')
        ->execute([$name, trim((string)($data['description'] ?? '')) ?: null, $id]);
    audit_log('task_template', $id, 'updated', ['name' => $before['name']], ['name' => $name]);
}

function delete_task_template(int $id): void
{
    $template = get_task_template($id);
    if (!$template) {
        throw new RuntimeException('Template not found.');
    }
    db()->prepare('DELETE FROM task_templates WHERE id = ?')->execute([$id]); // cascades to task_template_items
    audit_log('task_template', $id, 'deleted', ['name' => $template['name']], null);
}

/**
 * Creates or updates a single task within a template, depending on whether
 * $data['id'] is set. New items are appended to the end of the list.
 */
function save_task_template_item(array $data): int
{
    $templateId = (int)($data['template_id'] ?? 0);
    $template = get_task_template($templateId);
    if (!$template) {
        throw new RuntimeException('Template not found.');
    }
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Task title is required.');
    }
    // Plain text, not rich text — the template task form uses an ordinary
    // textarea, unlike the real task modal's Quill editor, so there's no
    // HTML to sanitize here. It still passes through create_activity()'s
    // own sanitize_html() call once applied to a project, same as any
    // other new task's description.
    $description = trim((string)($data['description'] ?? '')) ?: null;
    $priority = in_array($data['priority'] ?? 'normal', ACTIVITY_PRIORITIES, true) ? $data['priority'] : 'normal';
    $estimatedMinutes = ($data['estimated_minutes'] ?? '') !== '' ? max(0, (int)$data['estimated_minutes']) : null;
    $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
    $isMilestone = !empty($data['is_milestone']) ? 1 : 0;
    $isIssue = !empty($data['is_issue']) ? 1 : 0;

    $id = (int)($data['id'] ?? 0);
    if ($id) {
        $item = get_task_template_item($id);
        if (!$item || (int)$item['template_id'] !== $templateId) {
            throw new RuntimeException('Template task not found.');
        }
        db()->prepare(
            'UPDATE task_template_items SET title=?, description=?, priority=?, estimated_minutes=?, category_id=?, is_milestone=?, is_issue=? WHERE id=?'
        )->execute([$title, $description, $priority, $estimatedMinutes, $categoryId, $isMilestone, $isIssue, $id]);
        audit_log('task_template_item', $id, 'updated', null, ['title' => $title]);
        return $id;
    }

    $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM task_template_items WHERE template_id = ?');
    $stmt->execute([$templateId]);
    $maxOrder = (int)$stmt->fetchColumn();

    db()->prepare(
        'INSERT INTO task_template_items
            (template_id, title, description, priority, estimated_minutes, category_id, is_milestone, is_issue, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([$templateId, $title, $description, $priority, $estimatedMinutes, $categoryId, $isMilestone, $isIssue, $maxOrder + 10]);
    $newId = (int)db()->lastInsertId();
    audit_log('task_template_item', $newId, 'created', null, ['title' => $title, 'template_id' => $templateId]);
    return $newId;
}

function delete_task_template_item(int $id): void
{
    $item = get_task_template_item($id);
    if (!$item) {
        throw new RuntimeException('Template task not found.');
    }
    db()->prepare('DELETE FROM task_template_items WHERE id = ?')->execute([$id]);
    audit_log('task_template_item', $id, 'deleted', ['title' => $item['title']], null);
}

/**
 * Moves a template task one position up or down in its template's list by
 * swapping sort_order with its immediate neighbor — a no-op if it's already
 * at the top (moving up) or bottom (moving down).
 */
function move_task_template_item(int $id, string $direction): void
{
    $item = get_task_template_item($id);
    if (!$item) {
        throw new RuntimeException('Template task not found.');
    }
    $siblings = list_task_template_items((int)$item['template_id']);
    $index = null;
    foreach ($siblings as $i => $s) {
        if ((int)$s['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapIndex < 0 || $swapIndex >= count($siblings)) {
        return;
    }
    $a = $siblings[$index];
    $b = $siblings[$swapIndex];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE task_template_items SET sort_order = ? WHERE id = ?')->execute([$b['sort_order'], $a['id']]);
        $pdo->prepare('UPDATE task_template_items SET sort_order = ? WHERE id = ?')->execute([$a['sort_order'], $b['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Applies the chosen items of a template to a project — copies each into a
 * brand-new task via create_activity(), the same way clone_activity() does,
 * so template-created tasks get identical status/notification defaults to
 * any other new task. Assignee defaults to the project's owner (a sensible
 * starting point; can be reassigned afterward like any task) and requester
 * to whoever applied the template (they're the one asking for the work to
 * exist). Returns the list of newly created activity IDs, in template order.
 */
function apply_task_template_to_project(int $templateId, int $projectId, array $itemIds, int $requesterPersonId, int $createdByUserId): array
{
    $project = get_project($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }
    $itemIds = array_map('intval', $itemIds);
    if (!$itemIds) {
        return [];
    }
    $items = list_task_template_items($templateId);
    $created = [];
    foreach ($items as $item) {
        if (!in_array((int)$item['id'], $itemIds, true)) {
            continue;
        }
        $data = [
            'title' => $item['title'],
            'description' => $item['description'],
            'project_id' => $projectId,
            'assignee_id' => (int)$project['owner_id'],
            'requester_id' => $requesterPersonId,
            'estimated_minutes' => $item['estimated_minutes'],
            'priority' => $item['priority'],
            'category_id' => $item['category_id'],
            'is_milestone' => $item['is_milestone'],
            'is_issue' => $item['is_issue'],
        ];
        $created[] = create_activity($data, 'planned', $createdByUserId, false);
    }
    if ($created) {
        audit_log('task_template', $templateId, 'applied_to_project', null, [
            'project_id' => $projectId, 'activity_ids' => $created,
        ]);
    }
    return $created;
}

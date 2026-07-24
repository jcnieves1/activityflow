<?php
declare(strict_types=1);

/**
 * Admin-manageable task/activity statuses (Administration → Task Statuses).
 * `slug` is the stable key stored on activities.status and depended on by
 * business logic elsewhere (create_activity()'s defaults, the timer's
 * auto-transition to "in_progress", update_activity_status()'s completion/
 * progress bookkeeping). `label` is the free-text, admin-editable display
 * text. The four slugs business logic structurally depends on — planned,
 * in_progress, completed, cancelled — are seeded with is_system=1 and can
 * never be deleted (their label can still be renamed freely); the rest
 * (backlog, ready, blocked, waiting, and anything an admin adds) can be
 * deleted, reassigning any activities using them first.
 */

/**
 * Memoized per-request: this is read on nearly every page (status dropdowns,
 * board columns, badges). create/update/delete_task_status() pass
 * $forceRefresh after writing so a page that both mutates and re-renders in
 * the same request (the admin/statuses.php page itself) never serves a
 * stale list from before its own change.
 */
function list_task_statuses(bool $forceRefresh = false): array
{
    static $cache = null;
    if ($cache === null || $forceRefresh) {
        $cache = db()->query('SELECT * FROM task_statuses ORDER BY sort_order, id')->fetchAll();
    }
    return $cache;
}

function task_status_slugs(): array
{
    return array_column(list_task_statuses(), 'slug');
}

function get_task_status(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM task_statuses WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_task_status_by_slug(string $slug): ?array
{
    foreach (list_task_statuses() as $s) {
        if ($s['slug'] === $slug) {
            return $s;
        }
    }
    return null;
}

/** Looks up a task status's admin-editable display label; falls back to a readable guess for an orphaned/unknown slug rather than showing nothing. */
function task_status_label(string $slug): string
{
    $status = get_task_status_by_slug($slug);
    return $status ? $status['label'] : ucwords(str_replace('_', ' ', $slug));
}

/** Turns a label into a stable, unique, SQL-safe slug: lowercase, [a-z0-9_] only, de-duplicated with a numeric suffix if needed. */
function slugify_task_status(string $label): string
{
    $base = strtolower(trim($label));
    $base = preg_replace('/[^a-z0-9]+/', '_', $base);
    $base = trim($base, '_');
    if ($base === '') {
        $base = 'status';
    }
    $existing = task_status_slugs();
    $slug = $base;
    $i = 2;
    while (in_array($slug, $existing, true)) {
        $slug = $base . '_' . $i;
        $i++;
    }
    return $slug;
}

function create_task_status(string $label): array
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Status text is required.');
    }
    $slug = slugify_task_status($label);
    $maxOrder = 0;
    foreach (list_task_statuses() as $s) {
        $maxOrder = max($maxOrder, (int)$s['sort_order']);
    }
    db()->prepare('INSERT INTO task_statuses (slug, label, sort_order, is_system) VALUES (?, ?, ?, 0)')
        ->execute([$slug, $label, $maxOrder + 10]);
    $id = (int)db()->lastInsertId();
    audit_log('task_status', $id, 'created', null, ['slug' => $slug, 'label' => $label]);
    list_task_statuses(true);
    return get_task_status($id);
}

/** Renames a status's display text. The slug (and any business logic keyed to it) never changes. */
function update_task_status(int $id, string $label): array
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Status text is required.');
    }
    $before = get_task_status($id);
    if (!$before) {
        throw new RuntimeException('Status not found.');
    }
    db()->prepare('UPDATE task_statuses SET label = ? WHERE id = ?')->execute([$label, $id]);
    audit_log('task_status', $id, 'updated', ['label' => $before['label']], ['label' => $label]);
    list_task_statuses(true);
    return get_task_status($id);
}

/** How many activities currently use a given status slug — surfaced to the admin before deletion. */
function count_activities_with_status(string $slug): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM activities WHERE status = ?');
    $stmt->execute([$slug]);
    return (int)$stmt->fetchColumn();
}

/**
 * Deletes a status. If any activities currently use it, $replacementSlug is
 * required — those activities are reassigned to it first, in the same
 * transaction as the delete, so no task is ever left pointing at a status
 * that no longer exists. Returns ['needs_replacement' => true, 'count' =>
 * n] instead of deleting when activities are in use and no replacement was
 * given, so the caller (admin/statuses.php) can prompt for one.
 */
function delete_task_status(int $id, ?string $replacementSlug = null): array
{
    $status = get_task_status($id);
    if (!$status) {
        throw new RuntimeException('Status not found.');
    }
    if ($status['is_system']) {
        throw new RuntimeException('"' . $status['label'] . '" is a required system status and cannot be deleted. You can still rename it.');
    }

    $inUseCount = count_activities_with_status($status['slug']);
    if ($inUseCount > 0 && $replacementSlug === null) {
        return ['needs_replacement' => true, 'count' => $inUseCount];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($inUseCount > 0) {
            $replacement = get_task_status_by_slug((string)$replacementSlug);
            if (!$replacement) {
                throw new InvalidArgumentException('Choose a valid replacement status.');
            }
            if ($replacement['slug'] === $status['slug']) {
                throw new InvalidArgumentException('The replacement status must be different from the one being removed.');
            }
            $pdo->prepare('UPDATE activities SET status = ? WHERE status = ?')->execute([$replacement['slug'], $status['slug']]);
            audit_log('task_status', $id, 'activities_reassigned', ['status' => $status['slug']], [
                'status' => $replacement['slug'], 'count' => $inUseCount,
            ]);
        }
        $pdo->prepare('DELETE FROM task_statuses WHERE id = ?')->execute([$id]);
        audit_log('task_status', $id, 'deleted', ['slug' => $status['slug'], 'label' => $status['label']], null);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    list_task_statuses(true);

    return ['needs_replacement' => false, 'count' => $inUseCount];
}

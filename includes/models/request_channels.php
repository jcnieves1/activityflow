<?php
declare(strict_types=1);

/**
 * Admin-manageable request channels (Administration → Request Channels).
 * `slug` is the stable key stored on activities.request_channel. `label` is
 * the free-text, admin-editable display text. Unlike task_statuses, no
 * business logic anywhere keys off a specific request_channel slug, so every
 * default channel is seeded with is_system=0 — the column is kept only for
 * structural consistency with task_statuses and future-proofing, and every
 * channel (including the defaults) can be freely renamed or deleted, with
 * any activities using it reassigned first.
 */

/**
 * Memoized per-request: this is read on most pages that show or edit a task
 * (activity modal, quick-add, reports). create/update/delete_request_channel()
 * pass $forceRefresh after writing so a page that both mutates and re-renders
 * in the same request (admin/request_channels.php itself) never serves a
 * stale list from before its own change.
 */
function list_request_channels(bool $forceRefresh = false): array
{
    static $cache = null;
    if ($cache === null || $forceRefresh) {
        $cache = db()->query('SELECT * FROM request_channels ORDER BY sort_order, id')->fetchAll();
    }
    return $cache;
}

function request_channel_slugs(): array
{
    return array_column(list_request_channels(), 'slug');
}

function get_request_channel(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM request_channels WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_request_channel_by_slug(string $slug): ?array
{
    foreach (list_request_channels() as $c) {
        if ($c['slug'] === $slug) {
            return $c;
        }
    }
    return null;
}

/** Looks up a request channel's admin-editable display label; falls back to a readable guess for an orphaned/unknown slug, and an em-dash for null/empty. */
function request_channel_label(?string $channel): string
{
    if (!$channel) {
        return '—';
    }
    $found = get_request_channel_by_slug($channel);
    return $found ? $found['label'] : ucwords(str_replace('_', ' ', $channel));
}

/** Turns a label into a stable, unique, SQL-safe slug: lowercase, [a-z0-9_] only, de-duplicated with a numeric suffix if needed. */
function slugify_request_channel(string $label): string
{
    $base = strtolower(trim($label));
    $base = preg_replace('/[^a-z0-9]+/', '_', $base);
    $base = trim($base, '_');
    if ($base === '') {
        $base = 'channel';
    }
    $existing = request_channel_slugs();
    $slug = $base;
    $i = 2;
    while (in_array($slug, $existing, true)) {
        $slug = $base . '_' . $i;
        $i++;
    }
    return $slug;
}

function create_request_channel(string $label): array
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Channel text is required.');
    }
    $slug = slugify_request_channel($label);
    $maxOrder = 0;
    foreach (list_request_channels() as $c) {
        $maxOrder = max($maxOrder, (int)$c['sort_order']);
    }
    db()->prepare('INSERT INTO request_channels (slug, label, sort_order, is_system) VALUES (?, ?, ?, 0)')
        ->execute([$slug, $label, $maxOrder + 10]);
    $id = (int)db()->lastInsertId();
    audit_log('request_channel', $id, 'created', null, ['slug' => $slug, 'label' => $label]);
    list_request_channels(true);
    return get_request_channel($id);
}

/** Renames a channel's display text. The slug never changes. */
function update_request_channel(int $id, string $label): array
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Channel text is required.');
    }
    $before = get_request_channel($id);
    if (!$before) {
        throw new RuntimeException('Channel not found.');
    }
    db()->prepare('UPDATE request_channels SET label = ? WHERE id = ?')->execute([$label, $id]);
    audit_log('request_channel', $id, 'updated', ['label' => $before['label']], ['label' => $label]);
    list_request_channels(true);
    return get_request_channel($id);
}

/** How many activities currently use a given request channel slug — surfaced to the admin before deletion. */
function count_activities_with_request_channel(string $slug): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM activities WHERE request_channel = ?');
    $stmt->execute([$slug]);
    return (int)$stmt->fetchColumn();
}

/**
 * Deletes a request channel. If any activities currently use it,
 * $replacementSlug is required — those activities are reassigned to it
 * first, in the same transaction as the delete, so no task is ever left
 * pointing at a channel that no longer exists. Returns
 * ['needs_replacement' => true, 'count' => n] instead of deleting when
 * activities are in use and no replacement was given, so the caller
 * (admin/request_channels.php) can prompt for one.
 */
function delete_request_channel(int $id, ?string $replacementSlug = null): array
{
    $channel = get_request_channel($id);
    if (!$channel) {
        throw new RuntimeException('Channel not found.');
    }
    if ($channel['is_system']) {
        throw new RuntimeException('"' . $channel['label'] . '" is a required system channel and cannot be deleted. You can still rename it.');
    }

    $inUseCount = count_activities_with_request_channel($channel['slug']);
    if ($inUseCount > 0 && $replacementSlug === null) {
        return ['needs_replacement' => true, 'count' => $inUseCount];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($inUseCount > 0) {
            $replacement = get_request_channel_by_slug((string)$replacementSlug);
            if (!$replacement) {
                throw new InvalidArgumentException('Choose a valid replacement channel.');
            }
            if ($replacement['slug'] === $channel['slug']) {
                throw new InvalidArgumentException('The replacement channel must be different from the one being removed.');
            }
            $pdo->prepare('UPDATE activities SET request_channel = ? WHERE request_channel = ?')->execute([$replacement['slug'], $channel['slug']]);
            audit_log('request_channel', $id, 'activities_reassigned', ['request_channel' => $channel['slug']], [
                'request_channel' => $replacement['slug'], 'count' => $inUseCount,
            ]);
        }
        $pdo->prepare('DELETE FROM request_channels WHERE id = ?')->execute([$id]);
        audit_log('request_channel', $id, 'deleted', ['slug' => $channel['slug'], 'label' => $channel['label']], null);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    list_request_channels(true);

    return ['needs_replacement' => false, 'count' => $inUseCount];
}

<?php
declare(strict_types=1);

/**
 * Admin-manageable list of default phase names (Administration → Release
 * Phase Templates) applied, in order, whenever a new Release is created —
 * see includes/models/releases.php's generate_default_phases(). Changing
 * this list only affects releases created afterward; existing releases'
 * already-created phases (release_phases) are entirely separate rows and
 * are never touched by edits here. There is no is_system-style protection —
 * nothing else in the app keys off a specific template name, so any of
 * them (including all 8 defaults) can be freely renamed, reordered, or
 * removed. If the list is emptied entirely, new releases are simply
 * created with zero phases (see generate_default_phases()'s early return
 * and validate_release_dates()'s dynamic minimum-days check in releases.php).
 */

/** Memoized per-request: read on every release-creation form and whenever a release is actually created. */
function list_release_phase_templates(bool $forceRefresh = false): array
{
    static $cache = null;
    if ($cache === null || $forceRefresh) {
        $cache = db()->query('SELECT * FROM release_phase_templates ORDER BY sort_order, id')->fetchAll();
    }
    return $cache;
}

function get_release_phase_template(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM release_phase_templates WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_release_phase_template(string $name): array
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Phase name is required.');
    }
    $existing = list_release_phase_templates();
    foreach ($existing as $t) {
        if (strcasecmp($t['name'], $name) === 0) {
            throw new InvalidArgumentException('A default phase named "' . $name . '" already exists.');
        }
    }
    $maxOrder = 0;
    foreach ($existing as $t) {
        $maxOrder = max($maxOrder, (int)$t['sort_order']);
    }
    db()->prepare('INSERT INTO release_phase_templates (name, sort_order) VALUES (?, ?)')->execute([$name, $maxOrder + 10]);
    $id = (int)db()->lastInsertId();
    audit_log('release_phase_template', $id, 'created', null, ['name' => $name]);
    list_release_phase_templates(true);
    return get_release_phase_template($id);
}

function update_release_phase_template(int $id, string $name): array
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Phase name is required.');
    }
    $before = get_release_phase_template($id);
    if (!$before) {
        throw new RuntimeException('Default phase not found.');
    }
    foreach (list_release_phase_templates() as $t) {
        if ((int)$t['id'] !== $id && strcasecmp($t['name'], $name) === 0) {
            throw new InvalidArgumentException('A default phase named "' . $name . '" already exists.');
        }
    }
    db()->prepare('UPDATE release_phase_templates SET name = ? WHERE id = ?')->execute([$name, $id]);
    audit_log('release_phase_template', $id, 'updated', ['name' => $before['name']], ['name' => $name]);
    list_release_phase_templates(true);
    return get_release_phase_template($id);
}

function delete_release_phase_template(int $id): void
{
    $template = get_release_phase_template($id);
    if (!$template) {
        throw new RuntimeException('Default phase not found.');
    }
    db()->prepare('DELETE FROM release_phase_templates WHERE id = ?')->execute([$id]);
    audit_log('release_phase_template', $id, 'deleted', ['name' => $template['name']], null);
    list_release_phase_templates(true);
}

/**
 * Moves a template one position up or down in the list by swapping
 * sort_order with its immediate neighbor — a no-op if it's already at the
 * top (moving up) or bottom (moving down).
 */
function move_release_phase_template(int $id, string $direction): void
{
    $templates = list_release_phase_templates();
    $index = null;
    foreach ($templates as $i => $t) {
        if ((int)$t['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        throw new RuntimeException('Default phase not found.');
    }
    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapIndex < 0 || $swapIndex >= count($templates)) {
        return;
    }
    $a = $templates[$index];
    $b = $templates[$swapIndex];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE release_phase_templates SET sort_order = ? WHERE id = ?')->execute([$b['sort_order'], $a['id']]);
        $pdo->prepare('UPDATE release_phase_templates SET sort_order = ? WHERE id = ?')->execute([$a['sort_order'], $b['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit_log('release_phase_template', $id, 'reordered', ['sort_order' => $a['sort_order']], ['sort_order' => $b['sort_order']]);
    list_release_phase_templates(true);
}

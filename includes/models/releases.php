<?php
declare(strict_types=1);

/**
 * Releases (Administration → Releases): a company launch made up of several
 * project executions. Each release has its own start_date/end_date (the
 * "launch date"), a set of chronological phases (release_phases — named and
 * ordered from the admin-manageable release_phase_templates list at the
 * moment of creation; see includes/models/release_phase_templates.php and
 * generate_default_phases()), and zero or more associated projects. A
 * project belongs to at most one release at a time (projects.release_id).
 *
 * All of this is Administrator-only to manage — every write function here is
 * called exclusively from api/admin.php, which already gates the whole file
 * behind require_role([ROLE_ADMIN]) — but the read functions (list/get) are
 * also used to show a project's release read-only to any role that can view
 * the project, so nothing in this file itself enforces permissions.
 */

function list_releases(): array
{
    $sql = 'SELECT r.*,
                   (SELECT COUNT(*) FROM projects pr WHERE pr.release_id = r.id) AS project_count,
                   (SELECT COUNT(*) FROM release_phases rp WHERE rp.release_id = r.id) AS phase_count
            FROM releases r
            ORDER BY r.start_date DESC, r.id DESC';
    return db()->query($sql)->fetchAll();
}

function get_release(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM releases WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Basic start<=end and non-empty validation shared by create/update, plus a
 * minimum-span check sized to however many default phase templates are
 * currently configured (each needs at least one day) — so this scales
 * automatically as admins add/remove entries in Administration → Release
 * Phase Templates, rather than assuming a fixed phase count. If the
 * template list is ever emptied entirely, the minimum degrades to 1 day
 * (just start<=end), since generate_default_phases() then creates no phases
 * at all.
 */
function validate_release_dates(string $start, string $end): void
{
    if ($start === '' || $end === '') {
        throw new InvalidArgumentException('Start date and launch date are required.');
    }
    if ($start > $end) {
        throw new InvalidArgumentException('The start date must be on or before the launch date.');
    }
    $totalDays = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
    $templateNames = array_column(list_release_phase_templates(), 'name');
    $minDays = max(1, count($templateNames));
    if ($totalDays < $minDays) {
        throw new InvalidArgumentException(
            'The release must span at least ' . $minDays . ' day' . ($minDays === 1 ? '' : 's') .
            ', so each of its ' . count($templateNames) . ' default phase' . (count($templateNames) === 1 ? '' : 's') .
            ' (' . implode(', ', $templateNames) . ') can have at least one day.'
        );
    }
}

function create_release(array $data, ?int $createdBy): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Release name is required.');
    }
    $start = (string)($data['start_date'] ?? '');
    $end = (string)($data['end_date'] ?? '');
    validate_release_dates($start, $end);
    $description = trim((string)($data['description'] ?? ''));

    db()->prepare('INSERT INTO releases (name, description, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $description !== '' ? $description : null, $start, $end, $createdBy]);
    $id = (int)db()->lastInsertId();
    audit_log('release', $id, 'created', null, ['name' => $name, 'start_date' => $start, 'end_date' => $end]);

    generate_default_phases($id, $start, $end);

    return $id;
}

function update_release(int $id, array $data): void
{
    $before = get_release($id);
    if (!$before) {
        throw new RuntimeException('Release not found.');
    }
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Release name is required.');
    }
    $start = (string)($data['start_date'] ?? '');
    $end = (string)($data['end_date'] ?? '');
    validate_release_dates($start, $end);
    $description = trim((string)($data['description'] ?? ''));

    db()->prepare('UPDATE releases SET name=?, description=?, start_date=?, end_date=? WHERE id=?')
        ->execute([$name, $description !== '' ? $description : null, $start, $end, $id]);
    [$old, $new] = diff_fields($before, [
        'name' => $name, 'description' => $description, 'start_date' => $start, 'end_date' => $end,
    ]);
    audit_log('release', $id, 'updated', $old, $new);
}

/**
 * Deletes a release. Its phases are removed (they only exist to describe
 * this release), and any projects associated with it are disassociated —
 * never deleted — per the product requirement that removing a release must
 * never touch the projects that were part of it.
 */
function delete_release(int $id): bool
{
    $release = get_release($id);
    if (!$release) {
        return false;
    }
    $pdo = db();
    $projectCount = count(list_projects_in_release($id));
    $phaseCount = count(list_release_phases($id));

    $pdo->beginTransaction();
    try {
        audit_log('release', $id, 'deleted', [
            'name' => $release['name'], 'project_count' => $projectCount, 'phase_count' => $phaseCount,
        ], null);
        $pdo->prepare('UPDATE projects SET release_id = NULL WHERE release_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM release_phases WHERE release_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM releases WHERE id = ?')->execute([$id]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// -----------------------------------------------------------------------
// Phases
// -----------------------------------------------------------------------

function list_release_phases(int $releaseId): array
{
    $stmt = db()->prepare('SELECT * FROM release_phases WHERE release_id = ? ORDER BY start_date, id');
    $stmt->execute([$releaseId]);
    return $stmt->fetchAll();
}

function get_release_phase(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM release_phases WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Splits a release's [start,end] window into contiguous, non-overlapping
 * day ranges named and ordered from the current
 * Administration → Release Phase Templates list (release_phase_templates)
 * as a starting point — the largest-remainder method distributes any
 * leftover days to the earliest phases so every day in the release is
 * covered exactly once. Admins can freely re-date, rename, add to, or remove
 * from this set afterward via update/create/delete_release_phase(), and
 * changing the template list itself only affects releases created after
 * that change. If the template list is empty, no phases are created at all
 * — the release simply starts with none, and admins can add phases to it
 * manually via create_release_phase().
 */
function generate_default_phases(int $releaseId, string $startDate, string $endDate): void
{
    $names = array_column(list_release_phase_templates(), 'name');
    if (!$names) {
        return;
    }
    $count = count($names);
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $totalDays = $start->diff($end)->days + 1;
    $base = intdiv($totalDays, $count);
    $remainder = $totalDays % $count;

    $cursor = $start;
    $stmt = db()->prepare('INSERT INTO release_phases (release_id, name, start_date, end_date) VALUES (?, ?, ?, ?)');
    foreach ($names as $i => $name) {
        $len = $base + ($i < $remainder ? 1 : 0);
        $phaseStart = $cursor;
        $phaseEnd = $cursor->modify('+' . ($len - 1) . ' days');
        $stmt->execute([$releaseId, $name, $phaseStart->format('Y-m-d'), $phaseEnd->format('Y-m-d')]);
        $cursor = $phaseEnd->modify('+1 day');
    }
}

/**
 * Shared guard for create/update_release_phase(): the phase's dates must be
 * a valid range, must fall entirely within the parent release's start/end
 * window, and must not overlap any sibling phase (two ranges [a1,a2] and
 * [b1,b2] overlap when a1<=b2 AND b1<=a2 — touching/adjacent-day ranges,
 * like the auto-generated defaults, are NOT overlapping since each phase's
 * end day and the next phase's start day are distinct days).
 */
function validate_phase_dates(array $release, string $start, string $end, array $siblingPhases, ?int $excludePhaseId = null): void
{
    if ($start === '' || $end === '') {
        throw new InvalidArgumentException('Phase start and end dates are required.');
    }
    if ($start > $end) {
        throw new InvalidArgumentException('The phase start date must be on or before its end date.');
    }
    if ($start < $release['start_date'] || $end > $release['end_date']) {
        throw new InvalidArgumentException(
            'Phase dates must fall within the release\'s start (' . $release['start_date'] .
            ') and launch (' . $release['end_date'] . ') dates.'
        );
    }
    foreach ($siblingPhases as $p) {
        if ($excludePhaseId !== null && (int)$p['id'] === $excludePhaseId) {
            continue;
        }
        if ($start <= $p['end_date'] && $p['start_date'] <= $end) {
            throw new InvalidArgumentException(
                'Phase dates overlap with "' . $p['name'] . '" (' . $p['start_date'] . ' to ' . $p['end_date'] . ').'
            );
        }
    }
}

/** Manually adds a phase to a release — the "add phases for more flexibility" case, beyond the 4 auto-generated ones. */
function create_release_phase(int $releaseId, array $data): int
{
    $release = get_release($releaseId);
    if (!$release) {
        throw new RuntimeException('Release not found.');
    }
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Phase name is required.');
    }
    $start = (string)($data['start_date'] ?? '');
    $end = (string)($data['end_date'] ?? '');
    $siblings = list_release_phases($releaseId);
    validate_phase_dates($release, $start, $end, $siblings);

    db()->prepare('INSERT INTO release_phases (release_id, name, start_date, end_date) VALUES (?, ?, ?, ?)')
        ->execute([$releaseId, $name, $start, $end]);
    $id = (int)db()->lastInsertId();
    audit_log('release_phase', $id, 'created', null, ['release_id' => $releaseId, 'name' => $name, 'start_date' => $start, 'end_date' => $end]);
    return $id;
}

/** Renames and/or re-dates a phase, re-validated against its release's bounds and its (other) sibling phases every time. */
function update_release_phase(int $id, array $data): void
{
    $phase = get_release_phase($id);
    if (!$phase) {
        throw new RuntimeException('Phase not found.');
    }
    $release = get_release((int)$phase['release_id']);
    if (!$release) {
        throw new RuntimeException('Release not found.');
    }
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Phase name is required.');
    }
    $start = (string)($data['start_date'] ?? '');
    $end = (string)($data['end_date'] ?? '');
    $siblings = list_release_phases((int)$phase['release_id']);
    validate_phase_dates($release, $start, $end, $siblings, $id);

    db()->prepare('UPDATE release_phases SET name=?, start_date=?, end_date=? WHERE id=?')
        ->execute([$name, $start, $end, $id]);
    audit_log('release_phase', $id, 'updated',
        ['name' => $phase['name'], 'start_date' => $phase['start_date'], 'end_date' => $phase['end_date']],
        ['name' => $name, 'start_date' => $start, 'end_date' => $end]
    );
}

function delete_release_phase(int $id): void
{
    $phase = get_release_phase($id);
    if (!$phase) {
        throw new RuntimeException('Phase not found.');
    }
    db()->prepare('DELETE FROM release_phases WHERE id = ?')->execute([$id]);
    audit_log('release_phase', $id, 'deleted', ['release_id' => $phase['release_id'], 'name' => $phase['name']], null);
}

// -----------------------------------------------------------------------
// Release <-> Project association
// -----------------------------------------------------------------------

function list_projects_in_release(int $releaseId): array
{
    $stmt = db()->prepare(
        'SELECT pr.*, p.full_name AS owner_name FROM projects pr
         LEFT JOIN people p ON p.id = pr.owner_id
         WHERE pr.release_id = ? ORDER BY pr.name'
    );
    $stmt->execute([$releaseId]);
    return $stmt->fetchAll();
}

/** Projects with no release yet — the pool an admin can pick from to associate to a release. */
function list_unassigned_projects(): array
{
    $stmt = db()->query(
        'SELECT pr.*, p.full_name AS owner_name FROM projects pr
         LEFT JOIN people p ON p.id = pr.owner_id
         WHERE pr.release_id IS NULL ORDER BY pr.name'
    );
    return $stmt->fetchAll();
}

/**
 * Associates a currently-unassigned project to a release. Deliberately
 * refuses a project that already belongs to a (different) release — per the
 * product requirement, reassigning an already-associated project must go
 * through move_project_to_release() instead, which is an explicit, distinct
 * action so it's clear to the admin they're moving it away from wherever it
 * was, not just adding a second association.
 */
function associate_project_to_release(int $releaseId, int $projectId): void
{
    $release = get_release($releaseId);
    if (!$release) {
        throw new RuntimeException('Release not found.');
    }
    $project = get_project($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }
    if (!empty($project['release_id'])) {
        throw new InvalidArgumentException('This project already belongs to another release. Use "Move to another release" to reassign it.');
    }
    db()->prepare('UPDATE projects SET release_id = ? WHERE id = ?')->execute([$releaseId, $projectId]);
    audit_log('project', $projectId, 'release_associated', null, ['release_id' => $releaseId]);
}

/** Reassigns a project from whatever release it's currently in (if any) to a different one. */
function move_project_to_release(int $projectId, int $newReleaseId): void
{
    $project = get_project($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }
    $release = get_release($newReleaseId);
    if (!$release) {
        throw new RuntimeException('Release not found.');
    }
    if ((int)($project['release_id'] ?? 0) === $newReleaseId) {
        throw new InvalidArgumentException('That project is already part of this release.');
    }
    $oldReleaseId = $project['release_id'] ?? null;
    db()->prepare('UPDATE projects SET release_id = ? WHERE id = ?')->execute([$newReleaseId, $projectId]);
    audit_log('project', $projectId, 'release_moved', ['release_id' => $oldReleaseId], ['release_id' => $newReleaseId]);
}

function disassociate_project_from_release(int $projectId): void
{
    $project = get_project($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }
    if (empty($project['release_id'])) {
        throw new InvalidArgumentException('This project is not currently associated with any release.');
    }
    $oldReleaseId = $project['release_id'];
    db()->prepare('UPDATE projects SET release_id = NULL WHERE id = ?')->execute([$projectId]);
    audit_log('project', $projectId, 'release_disassociated', ['release_id' => $oldReleaseId], null);
}

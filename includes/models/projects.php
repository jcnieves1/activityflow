<?php
declare(strict_types=1);

function list_projects(array $filters = []): array
{
    $sql = 'SELECT pr.*, p.full_name AS owner_name,
                   (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = pr.id) AS member_count
            FROM projects pr
            LEFT JOIN people p ON p.id = pr.owner_id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND pr.status = ?';
        $params[] = $filters['status'];
    }
    if (isset($filters['is_archived'])) {
        $sql .= ' AND pr.is_archived = ?';
        $params[] = (int)$filters['is_archived'];
    }
    if (!empty($filters['owner_id'])) {
        $sql .= ' AND pr.owner_id = ?';
        $params[] = $filters['owner_id'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (pr.name LIKE ? OR pr.code LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        array_push($params, $like, $like);
    }
    $sql .= ' ORDER BY pr.created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_project(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT pr.*, p.full_name AS owner_name FROM projects pr
         LEFT JOIN people p ON p.id = pr.owner_id WHERE pr.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_project_by_code(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM projects WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_project(array $data, int $createdBy): int
{
    $stmt = db()->prepare(
        'INSERT INTO projects (name, code, description, owner_id, department_id, start_date, target_completion_date,
            priority, status, planned_effort_hours, color, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['name'], $data['code'], $data['description'] ?? null, $data['owner_id'],
        $data['department_id'] ?: null, $data['start_date'] ?: null, $data['target_completion_date'] ?: null,
        $data['priority'] ?? 'normal', $data['status'] ?? 'draft', $data['planned_effort_hours'] ?: null,
        $data['color'] ?? '#4361ee', $data['notes'] ?? null, $createdBy,
    ]);
    $id = (int)db()->lastInsertId();
    audit_log('project', $id, 'created', null, $data);

    // Owner is automatically a project_manager member.
    add_project_member($id, (int)$data['owner_id'], 'project_manager');

    return $id;
}

function update_project(int $id, array $data): void
{
    $before = get_project($id);
    $stmt = db()->prepare(
        'UPDATE projects SET name=?, code=?, description=?, owner_id=?, department_id=?, start_date=?,
            target_completion_date=?, actual_completion_date=?, priority=?, status=?, planned_effort_hours=?,
            color=?, notes=?, is_archived=? WHERE id=?'
    );
    $stmt->execute([
        $data['name'], $data['code'], $data['description'] ?? null, $data['owner_id'],
        $data['department_id'] ?: null, $data['start_date'] ?: null, $data['target_completion_date'] ?: null,
        $data['actual_completion_date'] ?: null, $data['priority'] ?? 'normal', $data['status'] ?? 'draft',
        $data['planned_effort_hours'] ?: null, $data['color'] ?? '#4361ee', $data['notes'] ?? null,
        !empty($data['is_archived']) ? 1 : 0, $id,
    ]);
    [$old, $new] = diff_fields($before, $data);
    audit_log('project', $id, 'updated', $old, $new);
}

function list_project_members(int $projectId): array
{
    $stmt = db()->prepare(
        'SELECT pm.*, p.full_name, p.job_title, p.email FROM project_members pm
         JOIN people p ON p.id = pm.person_id WHERE pm.project_id = ? ORDER BY pm.project_role, p.full_name'
    );
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function add_project_member(int $projectId, int $personId, string $role): void
{
    $stmt = db()->prepare(
        'INSERT INTO project_members (project_id, person_id, project_role) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE project_role = VALUES(project_role)'
    );
    $stmt->execute([$projectId, $personId, $role]);
    audit_log('project_member', $projectId, 'member_added', null, ['person_id' => $personId, 'role' => $role]);
    notify_person($personId, 'project_membership', 'Added to project', "You were added to a project as $role.", 'project', $projectId);
}

function remove_project_member(int $projectId, int $personId): void
{
    db()->prepare('DELETE FROM project_members WHERE project_id = ? AND person_id = ?')->execute([$projectId, $personId]);
    audit_log('project_member', $projectId, 'member_removed', ['person_id' => $personId], null);
}

/**
 * Duration-weighted (default) or simple task-count progress.
 * Cancelled tasks are excluded from both. Tasks without an estimate are
 * excluded from the duration-weighted denominator and flagged via 'warning'.
 */
function calculate_project_progress(int $projectId, string $method = 'duration_weighted'): array
{
    $stmt = db()->prepare(
        'SELECT completion_pct, estimated_minutes, status FROM activities WHERE project_id = ? AND status != "cancelled"'
    );
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll();

    $activeCount = count($tasks);
    $completedCount = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
    $missingEstimateCount = count(array_filter($tasks, fn($t) => $t['estimated_minutes'] === null));

    if ($method === 'simple_count') {
        $percent = $activeCount > 0 ? round($completedCount / $activeCount * 100, 1) : 0.0;
        return [
            'method' => 'simple_count',
            'percent' => $percent,
            'active_count' => $activeCount,
            'completed_count' => $completedCount,
            'missing_estimate_count' => $missingEstimateCount,
            'warning' => null,
        ];
    }

    $weightedSum = 0;
    $totalDuration = 0;
    foreach ($tasks as $t) {
        if ($t['estimated_minutes'] === null) {
            continue;
        }
        $weightedSum += (float)$t['completion_pct'] * (int)$t['estimated_minutes'];
        $totalDuration += (int)$t['estimated_minutes'];
    }
    $percent = $totalDuration > 0 ? round($weightedSum / $totalDuration, 1) : 0.0;

    $warning = null;
    if ($missingEstimateCount > 0 && $activeCount > 0) {
        $warning = "$missingEstimateCount of $activeCount task(s) have no time estimate and are excluded — progress may be understated.";
    }

    return [
        'method' => 'duration_weighted',
        'percent' => $percent,
        'active_count' => $activeCount,
        'completed_count' => $completedCount,
        'missing_estimate_count' => $missingEstimateCount,
        'warning' => $warning,
    ];
}

function project_task_stats(int $projectId): array
{
    $pdo = db();

    $byStatus = $pdo->prepare('SELECT status, COUNT(*) AS n FROM activities WHERE project_id = ? GROUP BY status');
    $byStatus->execute([$projectId]);

    $byAssignee = $pdo->prepare(
        'SELECT p.full_name, COUNT(*) AS n FROM activities a JOIN people p ON p.id = a.assignee_id
         WHERE a.project_id = ? AND a.status != "cancelled" GROUP BY p.id ORDER BY n DESC'
    );
    $byAssignee->execute([$projectId]);

    $unplanned = $pdo->prepare(
        'SELECT COUNT(*) AS n, COALESCE(SUM(estimated_minutes),0) AS minutes
         FROM activities WHERE project_id = ? AND activity_type = "unplanned"'
    );
    $unplanned->execute([$projectId]);

    $effort = $pdo->prepare(
        'SELECT COALESCE(SUM(estimated_minutes),0) AS planned_minutes,
                (SELECT COALESCE(SUM(te.duration_minutes),0) FROM time_entries te
                 JOIN activities a2 ON a2.id = te.activity_id WHERE a2.project_id = ?) AS actual_minutes
         FROM activities WHERE project_id = ?'
    );
    $effort->execute([$projectId, $projectId]);

    $requesters = $pdo->prepare(
        'SELECT p.full_name, COUNT(*) AS n FROM activities a JOIN people p ON p.id = a.requester_id
         WHERE a.project_id = ? GROUP BY p.id ORDER BY n DESC LIMIT 10'
    );
    $requesters->execute([$projectId]);

    $overdue = $pdo->prepare(
        'SELECT * FROM activities WHERE project_id = ? AND status NOT IN ("completed","cancelled")
         AND target_completion_at IS NOT NULL AND target_completion_at < NOW() ORDER BY target_completion_at'
    );
    $overdue->execute([$projectId]);

    $upcoming = $pdo->prepare(
        'SELECT * FROM activities WHERE project_id = ? AND status NOT IN ("completed","cancelled")
         AND target_completion_at IS NOT NULL AND target_completion_at >= NOW()
         ORDER BY target_completion_at LIMIT 10'
    );
    $upcoming->execute([$projectId]);

    return [
        'by_status'   => $byStatus->fetchAll(),
        'by_assignee' => $byAssignee->fetchAll(),
        'unplanned'   => $unplanned->fetch(),
        'effort'      => $effort->fetch(),
        'requesters'  => $requesters->fetchAll(),
        'overdue'     => $overdue->fetchAll(),
        'upcoming'    => $upcoming->fetchAll(),
    ];
}

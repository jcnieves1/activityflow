<?php
declare(strict_types=1);

function personal_dashboard_data(int $personId): array
{
    $pdo = db();
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    $todayPlanned = my_day_activities($personId, $today);
    $todayUnplanned = my_day_unplanned($personId, $today);
    $hours = my_day_hours_summary($personId, $today);
    $timer = active_timer_for($personId);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total, SUM(status="completed") AS done
         FROM activities WHERE assignee_id = ? AND DATE(COALESCE(planned_start_at, requested_at)) = ? AND is_active = 1'
    );
    $stmt->execute([$personId, $today]);
    $completionRow = $stmt->fetch();
    $completionRate = $completionRow['total'] > 0 ? round(($completionRow['done'] ?: 0) / $completionRow['total'] * 100, 1) : 0.0;

    $stmt = $pdo->prepare(
        'SELECT activity_type, SUM(estimated_minutes) AS est, COALESCE((
            SELECT SUM(te.duration_minutes) FROM time_entries te WHERE te.activity_id = a.id
         ),0) AS actual
         FROM activities a WHERE assignee_id = ? AND DATE(COALESCE(planned_start_at, requested_at)) BETWEEN ? AND ?
         GROUP BY activity_type'
    );
    $stmt->execute([$personId, $weekStart, date('Y-m-d', strtotime($weekStart . ' +6 day'))]);
    $weekly = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT * FROM activities WHERE assignee_id = ? AND status NOT IN ("completed","cancelled")
         AND target_completion_at IS NOT NULL AND target_completion_at < NOW() ORDER BY target_completion_at LIMIT 10'
    );
    $stmt->execute([$personId]);
    $overdue = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT * FROM activities WHERE assignee_id = ? AND status NOT IN ("completed","cancelled")
         AND target_completion_at IS NOT NULL AND target_completion_at >= NOW() ORDER BY target_completion_at LIMIT 8'
    );
    $stmt->execute([$personId]);
    $upcoming = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT req.full_name, COUNT(*) AS n FROM activities a JOIN people req ON req.id = a.requester_id
         WHERE a.assignee_id = ? AND a.activity_type = "unplanned" GROUP BY req.id ORDER BY n DESC LIMIT 5'
    );
    $stmt->execute([$personId]);
    $topRequesters = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT pr.name, COUNT(*) AS n FROM activities a JOIN projects pr ON pr.id = a.project_id
         WHERE a.assignee_id = ? AND a.activity_type = "unplanned" GROUP BY pr.id ORDER BY n DESC LIMIT 5'
    );
    $stmt->execute([$personId]);
    $interruptedProjects = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT request_channel, COUNT(*) AS n FROM activities WHERE assignee_id = ? AND activity_type = "unplanned"
         GROUP BY request_channel'
    );
    $stmt->execute([$personId]);
    $bySource = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT DISTINCT pr.id, pr.name, pr.color FROM projects pr JOIN project_members pm ON pm.project_id = pr.id
         WHERE pm.person_id = ? AND pr.is_archived = 0 LIMIT 6'
    );
    $stmt->execute([$personId]);
    $myProjects = $stmt->fetchAll();
    foreach ($myProjects as &$mp) {
        $mp['progress'] = calculate_project_progress((int)$mp['id']);
    }
    unset($mp);

    return compact(
        'todayPlanned', 'todayUnplanned', 'hours', 'timer', 'completionRate',
        'weekly', 'overdue', 'upcoming', 'topRequesters', 'interruptedProjects', 'bySource', 'myProjects'
    );
}

function manager_dashboard_data(array $filters = []): array
{
    $pdo = db();
    $where = 'WHERE a.is_active = 1';
    $params = [];
    if (!empty($filters['employee_id'])) { $where .= ' AND a.assignee_id = ?'; $params[] = $filters['employee_id']; }
    if (!empty($filters['project_id'])) { $where .= ' AND a.project_id = ?'; $params[] = $filters['project_id']; }
    if (!empty($filters['requester_id'])) { $where .= ' AND a.requester_id = ?'; $params[] = $filters['requester_id']; }
    if (!empty($filters['department_id'])) { $where .= ' AND asg.department_id = ?'; $params[] = $filters['department_id']; }
    if (!empty($filters['date_from'])) { $where .= ' AND DATE(COALESCE(a.planned_start_at, a.requested_at)) >= ?'; $params[] = $filters['date_from']; }
    if (!empty($filters['date_to'])) { $where .= ' AND DATE(COALESCE(a.planned_start_at, a.requested_at)) <= ?'; $params[] = $filters['date_to']; }

    $join = 'FROM activities a JOIN people asg ON asg.id = a.assignee_id';

    $stmt = $pdo->prepare(
        "SELECT asg.full_name, a.activity_type, COUNT(*) AS n, COALESCE(SUM(a.estimated_minutes),0) AS minutes
         $join $where GROUP BY asg.id, a.activity_type ORDER BY asg.full_name"
    );
    $stmt->execute($params);
    $workloadByEmployee = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT req.full_name, COUNT(*) AS n $join JOIN people req ON req.id = a.requester_id
         $where AND a.activity_type = 'unplanned' GROUP BY req.id ORDER BY n DESC LIMIT 10"
    );
    $stmt->execute($params);
    $unplannedByRequester = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT d.name, COUNT(*) AS n $join LEFT JOIN departments d ON d.id = asg.department_id
         $where AND a.activity_type = 'unplanned' GROUP BY d.id ORDER BY n DESC"
    );
    $stmt->execute($params);
    $unplannedByDepartment = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(a.estimated_minutes),0) AS est $join $where");
    $stmt->execute($params);
    $estVsActual = $stmt->fetch();

    $actualStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(te.duration_minutes),0) AS actual
         FROM time_entries te JOIN activities a ON a.id = te.activity_id JOIN people asg ON asg.id = a.assignee_id
         $where"
    );
    $actualStmt->execute($params);
    $estVsActual['actual'] = (int)$actualStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT asg.full_name, COUNT(*) AS n, COALESCE(AVG(i.time_lost_minutes),0) AS avg_lost
         FROM interruptions i JOIN activities a ON a.id = i.interrupting_activity_id JOIN people asg ON asg.id = a.assignee_id
         GROUP BY asg.id ORDER BY n DESC LIMIT 10"
    );
    $stmt->execute();
    $interruptionStats = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT * FROM projects WHERE is_archived = 0 AND status NOT IN ("completed","cancelled")
         AND target_completion_date IS NOT NULL AND target_completion_date < CURDATE() ORDER BY target_completion_date'
    );
    $stmt->execute();
    $lateProjects = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS n $join $where AND a.status NOT IN ('completed','cancelled')
         AND a.target_completion_at IS NOT NULL AND a.target_completion_at < NOW()"
    );
    $stmt->execute($params);
    $overdueCount = (int)$stmt->fetch()['n'];

    // "Work added after daily planning": unplanned activities requested after the employee's first planned item that day.
    $stmt = $pdo->prepare(
        "SELECT DATE(a.requested_at) AS d, COUNT(*) AS n $join $where AND a.activity_type = 'unplanned'
         GROUP BY DATE(a.requested_at) ORDER BY d DESC LIMIT 14"
    );
    $stmt->execute($params);
    $addedAfterPlanning = $stmt->fetchAll();

    $projects = list_projects(['is_archived' => 0]);
    foreach ($projects as &$p) {
        $p['progress'] = calculate_project_progress((int)$p['id']);
    }
    unset($p);

    return compact(
        'workloadByEmployee', 'unplannedByRequester', 'unplannedByDepartment', 'estVsActual',
        'interruptionStats', 'lateProjects', 'overdueCount', 'addedAfterPlanning', 'projects'
    );
}

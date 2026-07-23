<?php
declare(strict_types=1);

const REPORT_DEFINITIONS = [
    'planned_vs_unplanned'        => 'Planned vs. unplanned activities',
    'planned_vs_unplanned_hours'  => 'Planned vs. unplanned hours',
    'tasks_by_requester'          => 'Tasks by requester',
    'unplanned_by_requester'      => 'Unplanned tasks by requester',
    'unplanned_hours_by_requester'=> 'Unplanned hours by requester',
    'tasks_by_employee'           => 'Tasks by employee',
    'interruptions_by_employee'   => 'Interruptions by employee',
    'tasks_by_project'            => 'Tasks by project',
    'unplanned_by_project'        => 'Unplanned work added to projects',
    'adhoc_no_project'            => 'Ad-hoc tasks without projects',
    'estimated_vs_actual'         => 'Estimated vs. actual duration',
    'completion_rate'             => 'Task completion rate',
    'overdue_tasks'                => 'Overdue tasks',
    'project_progress'            => 'Project progress',
    'workload_by_department'      => 'Workload by department',
    'request_channel_analysis'    => 'Request channel analysis',
    'work_trends'                 => 'Daily and weekly work trends',
    'interrupted_delay'           => 'Interrupted activities and resulting delay',
    'added_after_planning'        => 'Tasks added after the day was planned',
    'requester_employee_matrix'   => 'Requester-to-employee activity matrix',
];

/** Builds a shared WHERE clause + params against the `activities a` table (joined to people/projects as needed by caller). */
function report_activity_where(array $f): array
{
    $where = ['a.is_active = 1'];
    $params = [];

    if (!empty($f['date_from'])) { $where[] = 'DATE(COALESCE(a.planned_start_at, a.requested_at)) >= ?'; $params[] = $f['date_from']; }
    if (!empty($f['date_to']))   { $where[] = 'DATE(COALESCE(a.planned_start_at, a.requested_at)) <= ?'; $params[] = $f['date_to']; }
    if (!empty($f['employee_id'])) { $where[] = 'a.assignee_id = ?'; $params[] = $f['employee_id']; }
    if (!empty($f['requester_id'])) { $where[] = 'a.requester_id = ?'; $params[] = $f['requester_id']; }
    if (!empty($f['project_id'])) { $where[] = 'a.project_id = ?'; $params[] = $f['project_id']; }
    if (!empty($f['department_id'])) { $where[] = 'asg.department_id = ?'; $params[] = $f['department_id']; }
    if (!empty($f['activity_type'])) { $where[] = 'a.activity_type = ?'; $params[] = $f['activity_type']; }
    if (isset($f['is_adhoc']) && $f['is_adhoc'] !== '') { $where[] = 'a.is_adhoc = ?'; $params[] = (int)$f['is_adhoc']; }
    if (!empty($f['status'])) { $where[] = 'a.status = ?'; $params[] = $f['status']; }
    if (!empty($f['priority'])) { $where[] = 'a.priority = ?'; $params[] = $f['priority']; }
    if (!empty($f['request_channel'])) { $where[] = 'a.request_channel = ?'; $params[] = $f['request_channel']; }
    if (!empty($f['category_id'])) { $where[] = 'a.category_id = ?'; $params[] = $f['category_id']; }

    return [implode(' AND ', $where), $params];
}

function run_report(string $key, array $filters): array
{
    $pdo = db();
    [$where, $params] = report_activity_where($filters);
    $base = 'FROM activities a
             JOIN people asg ON asg.id = a.assignee_id
             JOIN people req ON req.id = a.requester_id
             LEFT JOIN projects pr ON pr.id = a.project_id
             LEFT JOIN departments d ON d.id = asg.department_id';

    switch ($key) {
        case 'planned_vs_unplanned':
            $stmt = $pdo->prepare("SELECT a.activity_type AS 'Type', COUNT(*) AS 'Tasks' $base WHERE $where GROUP BY a.activity_type");
            $stmt->execute($params);
            return ['columns' => ['Type', 'Tasks'], 'rows' => $stmt->fetchAll()];

        case 'planned_vs_unplanned_hours':
            $stmt = $pdo->prepare("SELECT a.activity_type AS 'Type', ROUND(COALESCE(SUM(a.estimated_minutes),0)/60,1) AS 'Estimated hours' $base WHERE $where GROUP BY a.activity_type");
            $stmt->execute($params);
            return ['columns' => ['Type', 'Estimated hours'], 'rows' => $stmt->fetchAll()];

        case 'tasks_by_requester':
            $stmt = $pdo->prepare("SELECT req.full_name AS 'Requester', COUNT(*) AS 'Tasks' $base WHERE $where GROUP BY req.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Requester', 'Tasks'], 'rows' => $stmt->fetchAll()];

        case 'unplanned_by_requester':
            $stmt = $pdo->prepare("SELECT req.full_name AS 'Requester', COUNT(*) AS 'Unplanned tasks' $base WHERE $where AND a.activity_type='unplanned' GROUP BY req.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Requester', 'Unplanned tasks'], 'rows' => $stmt->fetchAll()];

        case 'unplanned_hours_by_requester':
            $stmt = $pdo->prepare("SELECT req.full_name AS 'Requester', ROUND(COALESCE(SUM(a.estimated_minutes),0)/60,1) AS 'Unplanned hours' $base WHERE $where AND a.activity_type='unplanned' GROUP BY req.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Requester', 'Unplanned hours'], 'rows' => $stmt->fetchAll()];

        case 'tasks_by_employee':
            $stmt = $pdo->prepare("SELECT asg.full_name AS 'Employee', COUNT(*) AS 'Tasks' $base WHERE $where GROUP BY asg.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Employee', 'Tasks'], 'rows' => $stmt->fetchAll()];

        case 'interruptions_by_employee':
            $stmt = $pdo->prepare(
                "SELECT asg.full_name AS 'Employee', COUNT(*) AS 'Interruptions', ROUND(AVG(i.time_lost_minutes),1) AS 'Avg minutes lost'
                 FROM interruptions i JOIN activities a ON a.id = i.interrupting_activity_id JOIN people asg ON asg.id = a.assignee_id
                 WHERE 1=1 " . ($filters['employee_id'] ?? '' ? 'AND a.assignee_id = ?' : '') . "
                 GROUP BY asg.id ORDER BY 2 DESC"
            );
            $stmt->execute($filters['employee_id'] ?? '' ? [$filters['employee_id']] : []);
            return ['columns' => ['Employee', 'Interruptions', 'Avg minutes lost'], 'rows' => $stmt->fetchAll()];

        case 'tasks_by_project':
            $stmt = $pdo->prepare("SELECT COALESCE(pr.name,'No project') AS 'Project', COUNT(*) AS 'Tasks' $base WHERE $where GROUP BY pr.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Project', 'Tasks'], 'rows' => $stmt->fetchAll()];

        case 'unplanned_by_project':
            $stmt = $pdo->prepare("SELECT pr.name AS 'Project', COUNT(*) AS 'Unplanned tasks', ROUND(COALESCE(SUM(a.estimated_minutes),0)/60,1) AS 'Hours' $base WHERE $where AND a.activity_type='unplanned' AND pr.id IS NOT NULL GROUP BY pr.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Project', 'Unplanned tasks', 'Hours'], 'rows' => $stmt->fetchAll()];

        case 'adhoc_no_project':
            $stmt = $pdo->prepare("SELECT a.title AS 'Task', asg.full_name AS 'Employee', req.full_name AS 'Requester', a.requested_at AS 'Requested' $base WHERE $where AND a.is_adhoc=1 AND a.project_id IS NULL ORDER BY a.requested_at DESC");
            $stmt->execute($params);
            return ['columns' => ['Task', 'Employee', 'Requester', 'Requested'], 'rows' => $stmt->fetchAll()];

        case 'estimated_vs_actual':
            $stmt = $pdo->prepare(
                "SELECT a.title AS 'Task', asg.full_name AS 'Employee', a.estimated_minutes AS 'Estimated (min)',
                 (SELECT COALESCE(SUM(te.duration_minutes),0) FROM time_entries te WHERE te.activity_id=a.id) AS 'Actual (min)'
                 $base WHERE $where ORDER BY a.updated_at DESC LIMIT 300"
            );
            $stmt->execute($params);
            return ['columns' => ['Task', 'Employee', 'Estimated (min)', 'Actual (min)'], 'rows' => $stmt->fetchAll()];

        case 'completion_rate':
            $stmt = $pdo->prepare(
                "SELECT asg.full_name AS 'Employee', SUM(a.status='completed') AS 'Completed', COUNT(*) AS 'Total',
                 ROUND(SUM(a.status='completed')/COUNT(*)*100,1) AS 'Completion %'
                 $base WHERE $where AND a.status != 'cancelled' GROUP BY asg.id ORDER BY 4 DESC"
            );
            $stmt->execute($params);
            return ['columns' => ['Employee', 'Completed', 'Total', 'Completion %'], 'rows' => $stmt->fetchAll()];

        case 'overdue_tasks':
            $stmt = $pdo->prepare(
                "SELECT a.title AS 'Task', asg.full_name AS 'Employee', COALESCE(pr.name,'—') AS 'Project', a.target_completion_at AS 'Was due'
                 $base WHERE $where AND a.status NOT IN ('completed','cancelled') AND a.target_completion_at < NOW() ORDER BY a.target_completion_at"
            );
            $stmt->execute($params);
            return ['columns' => ['Task', 'Employee', 'Project', 'Was due'], 'rows' => $stmt->fetchAll()];

        case 'project_progress':
            $projects = list_projects(['is_archived' => 0]);
            $rows = array_map(function ($p) {
                $prog = calculate_project_progress((int)$p['id']);
                return ['Project' => $p['name'], 'Status' => status_label($p['status']), 'Progress %' => $prog['percent'], 'Method' => $prog['method']];
            }, $projects);
            return ['columns' => ['Project', 'Status', 'Progress %', 'Method'], 'rows' => $rows];

        case 'workload_by_department':
            $stmt = $pdo->prepare("SELECT COALESCE(d.name,'Unassigned') AS 'Department', ROUND(COALESCE(SUM(a.estimated_minutes),0)/60,1) AS 'Hours' $base WHERE $where GROUP BY d.id ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Department', 'Hours'], 'rows' => $stmt->fetchAll()];

        case 'request_channel_analysis':
            $stmt = $pdo->prepare("SELECT COALESCE(a.request_channel,'Unspecified') AS 'Channel', COUNT(*) AS 'Tasks', ROUND(COALESCE(SUM(a.estimated_minutes),0)/60,1) AS 'Hours' $base WHERE $where GROUP BY a.request_channel ORDER BY 2 DESC");
            $stmt->execute($params);
            return ['columns' => ['Channel', 'Tasks', 'Hours'], 'rows' => $stmt->fetchAll()];

        case 'work_trends':
            $stmt = $pdo->prepare("SELECT DATE(COALESCE(a.planned_start_at,a.requested_at)) AS 'Date', a.activity_type AS 'Type', COUNT(*) AS 'Tasks' $base WHERE $where GROUP BY 1,2 ORDER BY 1 DESC LIMIT 60");
            $stmt->execute($params);
            return ['columns' => ['Date', 'Type', 'Tasks'], 'rows' => $stmt->fetchAll()];

        case 'interrupted_delay':
            $stmt = $pdo->prepare(
                "SELECT a.title AS 'Interrupted task', asg.full_name AS 'Employee', i.time_lost_minutes AS 'Minutes lost',
                 i.impact_on_target_date AS 'Impact on target date', i.was_resumed AS 'Resumed'
                 FROM interruptions i JOIN activities a ON a.id = i.interrupted_activity_id JOIN people asg ON asg.id = a.assignee_id
                 ORDER BY i.created_at DESC LIMIT 200"
            );
            $stmt->execute();
            return ['columns' => ['Interrupted task', 'Employee', 'Minutes lost', 'Impact on target date', 'Resumed'], 'rows' => $stmt->fetchAll()];

        case 'added_after_planning':
            $stmt = $pdo->prepare(
                "SELECT a.title AS 'Task', asg.full_name AS 'Employee', a.requested_at AS 'Requested at' $base
                 WHERE $where AND a.activity_type='unplanned'
                 AND EXISTS (SELECT 1 FROM activities p2 WHERE p2.assignee_id=a.assignee_id AND p2.activity_type='planned'
                             AND DATE(p2.planned_start_at)=DATE(a.requested_at) AND p2.created_at < a.created_at)
                 ORDER BY a.requested_at DESC LIMIT 200"
            );
            $stmt->execute($params);
            return ['columns' => ['Task', 'Employee', 'Requested at'], 'rows' => $stmt->fetchAll()];

        case 'requester_employee_matrix':
            $stmt = $pdo->prepare("SELECT req.full_name AS requester, asg.full_name AS employee, COUNT(*) AS n $base WHERE $where GROUP BY req.id, asg.id");
            $stmt->execute($params);
            $raw = $stmt->fetchAll();
            $employees = array_values(array_unique(array_column($raw, 'employee')));
            sort($employees);
            $matrix = [];
            foreach ($raw as $r) { $matrix[$r['requester']][$r['employee']] = (int)$r['n']; }
            $rows = [];
            foreach ($matrix as $requester => $counts) {
                $row = ['Requester' => $requester];
                foreach ($employees as $emp) { $row[$emp] = $counts[$emp] ?? 0; }
                $rows[] = $row;
            }
            return ['columns' => array_merge(['Requester'], $employees), 'rows' => $rows];

        default:
            return ['columns' => [], 'rows' => []];
    }
}

/**
 * Per-requester metrics for the Requester Analytics page (section 16).
 * Always returns the date range and sample size alongside the figures.
 */
function requester_analytics(array $filters): array
{
    [$where, $params] = report_activity_where($filters);
    $pdo = db();

    $sql = "SELECT
                req.id AS requester_id, req.full_name AS requester_name,
                COUNT(*) AS total_tasks,
                SUM(a.activity_type='planned') AS planned_tasks,
                SUM(a.activity_type='unplanned') AS unplanned_tasks,
                ROUND(COALESCE(SUM(a.estimated_minutes),0)/60,1) AS estimated_hours,
                ROUND(COALESCE((SELECT SUM(te.duration_minutes) FROM time_entries te
                    JOIN activities a2 ON a2.id = te.activity_id WHERE a2.requester_id = req.id),0)/60,1) AS actual_hours,
                COUNT(DISTINCT a.assignee_id) AS employees_affected,
                COUNT(DISTINCT a.project_id) AS projects_affected,
                SUM(a.project_id IS NULL) AS tasks_without_project,
                SUM(a.priority='urgent') AS urgent_tasks,
                ROUND(AVG(CASE WHEN a.target_completion_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.requested_at, a.target_completion_at) END),0) AS avg_notice_minutes,
                ROUND(SUM(a.activity_type='unplanned')/COUNT(*)*100,1) AS pct_unplanned
            FROM activities a
            JOIN people asg ON asg.id = a.assignee_id
            JOIN people req ON req.id = a.requester_id
            LEFT JOIN projects pr ON pr.id = a.project_id
            LEFT JOIN departments d ON d.id = asg.department_id
            WHERE $where
            GROUP BY req.id
            ORDER BY unplanned_tasks DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Average interruption duration + delayed activities, per requester (via their unplanned tasks that interrupted work).
    $interruptStmt = $pdo->prepare(
        'SELECT a.requester_id, ROUND(AVG(i.time_lost_minutes),1) AS avg_interruption_minutes,
                SUM(i.impact_on_target_date IS NOT NULL AND i.impact_on_target_date NOT LIKE "None%") AS delayed_count
         FROM interruptions i JOIN activities a ON a.id = i.interrupting_activity_id
         GROUP BY a.requester_id'
    );
    $interruptStmt->execute();
    $interruptByRequester = [];
    foreach ($interruptStmt->fetchAll() as $r) {
        $interruptByRequester[$r['requester_id']] = $r;
    }

    foreach ($rows as &$row) {
        $extra = $interruptByRequester[$row['requester_id']] ?? null;
        $row['avg_interruption_minutes'] = $extra['avg_interruption_minutes'] ?? null;
        $row['delayed_count'] = $extra['delayed_count'] ?? 0;
    }
    unset($row);

    return $rows;
}

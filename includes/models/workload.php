<?php
declare(strict_types=1);

/**
 * Workload comparison: for a chosen roster of people (or everyone active),
 * how many tasks (planned or unplanned, filtered by status and a date
 * window) are currently on their plate, regardless of which project each
 * task belongs to — used to compare who's free vs. overloaded before
 * assigning new work.
 */

/** Admin/PM only — this is a resourcing/management view across the whole team, not a personal one. */
function can_view_workload(): bool
{
    return is_admin() || is_pm();
}

/**
 * @param array $filters {
 *   person_id_in?: int[]  Explicit roster to show. Empty/absent = every active person.
 *   status_in?: string[]  Task status slugs to include. Empty/absent = every status.
 *   date_from?: string    'Y-m-d'. A task is included if its effective end is on/after this date.
 *   date_to?: string      'Y-m-d'. A task is included if its effective start is on/before this date.
 *   is_issue?: string     '1' to show only tasks tagged as an issue, '0' for only non-issues, '' or absent = both.
 *   order?: string        'asc' (least busy first) or 'desc' (most busy first). Default 'asc'.
 * }
 * @return array<int, array{person_id:int, person_name:string, job_title:string,
 *   task_count:int, tasks: array<int, array{id:int,title:string,project_name:?string,
 *   status:string,priority:string,activity_type:string,completion_pct:int,is_issue:bool}>}>
 */
function workload_summary(array $filters = []): array
{
    $personIds = array_values(array_unique(array_map('intval', $filters['person_id_in'] ?? [])));
    $statusSlugs = $filters['status_in'] ?? [];
    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    $dateTo = trim((string)($filters['date_to'] ?? ''));
    $isIssue = $filters['is_issue'] ?? '';
    $order = (strtolower((string)($filters['order'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';

    // Roster: an explicit selection, or every active person — either way, everyone
    // in the roster is shown even with zero matching tasks, so a person with a
    // completely free plate is visible as a valid "assign to them" candidate.
    if ($personIds) {
        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $stmt = db()->prepare("SELECT * FROM people WHERE id IN ($placeholders) AND is_active = 1 ORDER BY full_name");
        $stmt->execute($personIds);
    } else {
        $stmt = db()->prepare('SELECT * FROM people WHERE is_active = 1 ORDER BY full_name');
        $stmt->execute();
    }
    $people = $stmt->fetchAll();
    if (!$people) {
        return [];
    }

    $rosterIds = array_map('intval', array_column($people, 'id'));
    $ph = implode(',', array_fill(0, count($rosterIds), '?'));
    $sql = "SELECT a.id, a.title, a.assignee_id, a.status, a.priority, a.activity_type, a.completion_pct, a.is_issue,
                   pr.name AS project_name
            FROM activities a
            LEFT JOIN projects pr ON pr.id = a.project_id
            WHERE a.is_active = 1 AND a.assignee_id IN ($ph)";
    $params = $rosterIds;

    if ($statusSlugs) {
        $validStatuses = array_values(array_intersect(task_status_slugs(), $statusSlugs));
        if ($validStatuses) {
            $sph = implode(',', array_fill(0, count($validStatuses), '?'));
            $sql .= " AND a.status IN ($sph)";
            array_push($params, ...$validStatuses);
        }
    }
    if ($isIssue !== '') {
        $sql .= ' AND a.is_issue = ?';
        $params[] = (int)$isIssue;
    }
    // Overlap test against the selected time frame, mirroring the vacation
    // overlap check (find_overlapping_vacation()): a task counts if its
    // effective end is on/after date_from AND its effective start is on/before
    // date_to. Leaving either bound blank simply drops that half of the check.
    if ($dateFrom !== '') {
        $sql .= ' AND COALESCE(a.target_completion_at, a.planned_start_at, a.requested_at) >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $sql .= ' AND COALESCE(a.planned_start_at, a.requested_at) <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }
    $sql .= ' ORDER BY a.title';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    $tasksByPerson = [];
    foreach ($tasks as $t) {
        $tasksByPerson[(int)$t['assignee_id']][] = [
            'id' => (int)$t['id'],
            'title' => $t['title'],
            'project_name' => $t['project_name'],
            'status' => $t['status'],
            'priority' => $t['priority'],
            'activity_type' => $t['activity_type'],
            'completion_pct' => (int)$t['completion_pct'],
            'is_issue' => (bool)$t['is_issue'],
        ];
    }

    $result = [];
    foreach ($people as $p) {
        $personTasks = $tasksByPerson[(int)$p['id']] ?? [];
        $result[] = [
            'person_id' => (int)$p['id'],
            'person_name' => $p['full_name'],
            'job_title' => $p['job_title'] ?? '',
            'task_count' => count($personTasks),
            'tasks' => $personTasks,
        ];
    }

    usort($result, function ($a, $b) use ($order) {
        $cmp = $a['task_count'] <=> $b['task_count'];
        if ($cmp === 0) {
            $cmp = strcasecmp($a['person_name'], $b['person_name']);
        }
        return $order === 'DESC' ? -$cmp : $cmp;
    });

    return $result;
}

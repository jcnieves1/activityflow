<?php
declare(strict_types=1);

/**
 * Data for the Mind Map view: a Release -> Project -> Task -> assigned-person
 * tree the client renders as an interactive graph (see assets/js/mindmap.js).
 * Read-only and open to every logged-in role (see mindmap.php) — but
 * project-level visibility is still enforced per project via
 * can_view_project(), the same restriction Team Activities applies for
 * non-admin/non-viewer users, so this never leaks a project's tasks to
 * someone who couldn't already see them elsewhere.
 */

/**
 * @param array $filters {
 *   release_id_in?: int[]   Narrow to these releases (and their projects/tasks). Empty = every release.
 *   project_id_in?: int[]   Narrow to these projects (and their tasks). Empty = every visible project.
 *   status_in?: string[]    Narrow tasks to these status slugs. Empty = every status.
 *   assignee_id_in?: int[]  Narrow tasks to these assignees. Empty = everyone.
 * }
 * @return array{
 *   releases: array<int, array{id:int,name:string}>,
 *   projects: array<int, array{id:int,name:string,release_id:?int,color:string,completion_pct:float}>,
 *   tasks: array<int, array{id:int,title:string,status:string,priority:string,project_id:?int,project_color:?string,assignee_id:int,is_issue:bool,completion_pct:float}>,
 *   people: array<int, array{id:int,name:string,avatar_url:?string}>,
 *   has_no_release_bucket: bool,
 *   has_no_project_bucket: bool,
 * }
 */
function mindmap_data(array $filters = []): array
{
    $releaseFilter = array_map('intval', $filters['release_id_in'] ?? []);
    $projectFilter = array_map('intval', $filters['project_id_in'] ?? []);
    $statusFilter = $filters['status_in'] ?? [];
    $assigneeFilter = array_map('intval', $filters['assignee_id_in'] ?? []);
    $releaseFilterActive = (bool)$releaseFilter;
    $projectFilterActive = (bool)$projectFilter;
    // Once the user has explicitly narrowed by release or project, show the
    // full structure of what they asked for — including branches with no
    // currently-matching tasks — rather than silently hiding empty projects,
    // which would look like the filter picked the wrong thing. With no such
    // filter, prune anything with zero visible tasks so the default "whole
    // landscape" view doesn't get cluttered with long-dormant projects.
    $explicitProjectContext = $releaseFilterActive || $projectFilterActive;

    // Every non-archived project this user is allowed to see, narrowed by
    // the release/project filters (if given).
    $projects = [];
    foreach (list_projects(['is_archived' => 0]) as $p) {
        if (!can_view_project($p)) {
            continue;
        }
        if ($releaseFilterActive && (!$p['release_id'] || !in_array((int)$p['release_id'], $releaseFilter, true))) {
            continue;
        }
        if ($projectFilterActive && !in_array((int)$p['id'], $projectFilter, true)) {
            continue;
        }
        $projects[(int)$p['id']] = $p;
    }

    // Tasks matching the status/assignee filters. "No project" tasks are
    // dropped once a release/project filter is active — they can never
    // satisfy a specific release/project selection.
    $taskFilters = [];
    if ($statusFilter) {
        $taskFilters['status_in'] = $statusFilter;
    }
    if ($assigneeFilter) {
        $taskFilters['assignee_id_in'] = $assigneeFilter;
    }
    $tasks = [];
    foreach (list_activities($taskFilters) as $t) {
        $projectId = $t['project_id'] !== null ? (int)$t['project_id'] : null;
        if ($projectId === null) {
            if ($explicitProjectContext) {
                continue;
            }
            // A project-less task has no membership to check, so fall back
            // to activity_is_visible()'s own rule: broad-visibility roles
            // (admin/PM/viewer) see it regardless, but a restricted
            // Employee only sees it if they're the assignee or requester —
            // it no longer defaults to "visible to everyone" just because
            // it isn't tied to a project.
            if (!activity_is_visible($t)) {
                continue;
            }
        } elseif (!isset($projects[$projectId])) {
            continue;
        }
        $tasks[] = $t;
    }

    $usedProjectIds = [];
    $hasNoProjectBucket = false;
    foreach ($tasks as $t) {
        if ($t['project_id'] !== null) {
            $usedProjectIds[(int)$t['project_id']] = true;
        } else {
            $hasNoProjectBucket = true;
        }
    }

    $projectNodes = [];
    $usedReleaseIds = [];
    $hasNoReleaseBucket = false;
    foreach ($projects as $pid => $p) {
        if (!$explicitProjectContext && !isset($usedProjectIds[$pid])) {
            continue;
        }
        $releaseId = $p['release_id'] ? (int)$p['release_id'] : null;
        $projectNodes[] = [
            'id' => $pid,
            'name' => $p['name'],
            'release_id' => $releaseId,
            'color' => $p['color'] ?: '#4361ee',
            // The project's own overall completion (all of its tasks,
            // duration-weighted) — independent of the Mind Map's status/
            // assignee filters, so it always reflects the project's real
            // progress rather than just the currently-visible subset.
            'completion_pct' => (float)(calculate_project_progress($pid)['percent'] ?? 0),
        ];
        if ($releaseId) {
            $usedReleaseIds[$releaseId] = true;
        } else {
            $hasNoReleaseBucket = true;
        }
    }

    $releaseNodes = [];
    foreach (list_releases() as $r) {
        $rid = (int)$r['id'];
        $keep = $releaseFilterActive ? in_array($rid, $releaseFilter, true) : isset($usedReleaseIds[$rid]);
        if (!$keep) {
            continue;
        }
        $releaseNodes[] = ['id' => $rid, 'name' => $r['name']];
    }

    $taskNodes = [];
    $peopleById = [];
    foreach ($tasks as $t) {
        $taskNodes[] = [
            'id' => (int)$t['id'],
            'title' => $t['title'],
            'status' => $t['status'],
            'priority' => $t['priority'],
            'project_id' => $t['project_id'] !== null ? (int)$t['project_id'] : null,
            'project_color' => $t['project_color'] ?: null,
            'assignee_id' => (int)$t['assignee_id'],
            'is_issue' => !empty($t['is_issue']),
            'completion_pct' => (float)($t['completion_pct'] ?? 0),
        ];
        $peopleById[(int)$t['assignee_id']] = ['name' => $t['assignee_name'], 'avatar_path' => $t['assignee_avatar_path'] ?? null];
    }
    $peopleNodes = [];
    foreach ($peopleById as $id => $person) {
        $peopleNodes[] = ['id' => $id, 'name' => $person['name'], 'avatar_url' => avatar_url($person['avatar_path'])];
    }

    return [
        'releases' => $releaseNodes,
        'projects' => $projectNodes,
        'tasks' => $taskNodes,
        'people' => $peopleNodes,
        'has_no_release_bucket' => $hasNoReleaseBucket,
        'has_no_project_bucket' => $hasNoProjectBucket,
    ];
}

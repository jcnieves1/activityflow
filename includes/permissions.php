<?php
declare(strict_types=1);

/**
 * Role-based authorization. Every write path (pages AND api/*.php) must call
 * one of the require_* functions here — the UI hiding a control is never
 * sufficient on its own.
 */

const ROLE_ADMIN    = 'administrator';
const ROLE_PM       = 'project_manager';
const ROLE_EMPLOYEE = 'employee';
const ROLE_VIEWER   = 'viewer';

function user_roles(): array
{
    return $_SESSION['user']['roles'] ?? [];
}

function user_has_role(string $role): bool
{
    return in_array($role, user_roles(), true);
}

function is_admin(): bool
{
    return user_has_role(ROLE_ADMIN);
}

function is_pm(): bool
{
    return user_has_role(ROLE_PM);
}

function is_viewer_only(): bool
{
    $roles = user_roles();
    return count($roles) > 0 && $roles === [ROLE_VIEWER];
}

function current_person_id(): ?int
{
    return $_SESSION['user']['person_id'] ?? null;
}

/**
 * True while an administrator is impersonating another user — i.e. the
 * session's current $_SESSION['user'] is the impersonated account, but the
 * originating admin's identity is stashed in $_SESSION['impersonator'] (set
 * by api/impersonate.php's 'start' action) so the session can be restored
 * later via 'stop'. Deliberately session-based (not a DB flag) so it can
 * never outlive the browser session it started in.
 */
function is_impersonating(): bool
{
    return !empty($_SESSION['impersonator']);
}

/** The original admin's stashed identity (id/full_name/email), or null if not impersonating. */
function impersonator_info(): ?array
{
    return $_SESSION['impersonator'] ?? null;
}

function deny(string $message = 'You do not have permission to perform this action.'): void
{
    if (is_json_request() || (($_SERVER['REQUEST_URI'] ?? '') && str_contains($_SERVER['REQUEST_URI'], '/api/'))) {
        json_error($message, 403);
    }
    http_response_code(403);
    require __DIR__ . '/../403.php';
    exit;
}

function require_role(array $roles): void
{
    foreach ($roles as $role) {
        if (user_has_role($role)) {
            return;
        }
    }
    deny();
}

/** Read-only actions: any authenticated role may view permitted data. Write paths must call require_role explicitly. */
function require_login_or_deny(): void
{
    if (empty($_SESSION['user'])) {
        if (is_json_request()) {
            json_error('Authentication required.', 401);
        }
        redirect('login.php');
    }
}

function can_manage_project(array $project): bool
{
    if (is_admin()) {
        return true;
    }
    if (is_pm() && (int)$project['owner_id'] === (int)current_person_id()) {
        return true;
    }
    return false;
}

function can_edit_activity(array $activity): bool
{
    if (is_admin()) {
        return true;
    }
    $personId = current_person_id();
    if ((int)$activity['assignee_id'] === (int)$personId || (int)$activity['created_by'] === (int)($_SESSION['user']['id'] ?? 0)) {
        return true;
    }
    if (is_pm() && !empty($activity['project_id'])) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT owner_id FROM projects WHERE id = ?');
        $stmt->execute([$activity['project_id']]);
        $project = $stmt->fetch();
        if ($project && (int)$project['owner_id'] === (int)$personId) {
            return true;
        }
    }
    return false;
}

/**
 * Deletion follows the same rule set as editing: administrators can delete any
 * task; a project manager can delete any task in a project they own; everyone
 * else (regular employees) can only delete a task they're the assignee or
 * original creator of. Kept as its own named function (rather than calling
 * can_edit_activity() directly at every call site) so delete rules can diverge
 * from edit rules later without hunting down every caller.
 */
function can_delete_activity(array $activity): bool
{
    return can_edit_activity($activity);
}

/**
 * Governs whether tasks may be cloned or moved INTO this project. Admins and
 * project managers are treated as having a blanket "qualified role" for this —
 * deliberately broader than can_manage_project(), which restricts full project
 * edit/delete to the project's own owning PM. Anyone else must already be a
 * member of the destination project. This only covers the destination side;
 * callers must separately confirm the user can touch the source task (e.g. via
 * can_edit_activity()) before allowing a clone/move out of it.
 */
function can_add_task_to_project(array $project): bool
{
    if (is_admin() || is_pm()) {
        return true;
    }
    return is_project_member((int)$project['id']);
}

function can_reclassify_activity(array $activity): bool
{
    return is_admin() || can_manage_project(['owner_id' => project_owner_id($activity['project_id'] ?? null)]);
}

/**
 * Comment edits are restricted to the comment's own author — intentionally not
 * even administrators, per an explicit product decision to keep comment history
 * trustworthy (no one editing someone else's words, including for moderation).
 */
function can_edit_comment(array $comment): bool
{
    return (int)$comment['author_id'] === (int)(current_user()['id'] ?? 0);
}

function project_owner_id(?int $projectId): ?int
{
    if (!$projectId) {
        return null;
    }
    static $cache = [];
    if (!isset($cache[$projectId])) {
        $stmt = db()->prepare('SELECT owner_id FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        $cache[$projectId] = $row ? (int)$row['owner_id'] : null;
    }
    return $cache[$projectId];
}

/** A vacation entry can be managed (edited/deleted) by an administrator, or by the person it belongs to — no one else, including their manager or a PM. */
function can_manage_vacation(array $vacation): bool
{
    return is_admin() || (int)$vacation['person_id'] === (int)current_person_id();
}

function is_project_member(int $projectId, ?int $personId = null): bool
{
    $personId = $personId ?? current_person_id();
    if (!$personId) {
        return false;
    }
    $stmt = db()->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND person_id = ?');
    $stmt->execute([$projectId, $personId]);
    return (bool)$stmt->fetchColumn();
}

/** Read visibility for a single activity: admins/viewers see all; otherwise project members, the assignee/requester, or anyone for project-less (org-wide) activities. */
function activity_is_visible(array $activity): bool
{
    if (is_admin() || user_has_role(ROLE_VIEWER)) {
        return true;
    }
    $personId = current_person_id();
    if ($personId && ((int)$activity['assignee_id'] === (int)$personId || (int)$activity['requester_id'] === (int)$personId)) {
        return true;
    }
    if (empty($activity['project_id'])) {
        return true;
    }
    $stmt = db()->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$activity['project_id']]);
    $project = $stmt->fetch();
    return $project ? can_view_project($project) : false;
}

function can_view_project(array $project): bool
{
    if (is_admin() || user_has_role(ROLE_VIEWER)) {
        return true;
    }
    if ((int)$project['owner_id'] === (int)current_person_id()) {
        return true;
    }
    return is_project_member((int)$project['id']);
}

# Security notes

## Authentication

- Passwords are hashed with `password_hash()` (bcrypt by default) and
  verified with `password_verify()`. Plain-text passwords are never stored or
  logged.
- Login is rate-limited: `login_attempts` records every attempt; after
  `security.login_max_attempts` failures for an email within
  `login_lockout_minutes`, further attempts are rejected with a generic
  message until the window passes.
- Session ID is regenerated on every successful login (`session_regenerate_id(true)`)
  to prevent session fixation.
- Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` automatically
  when served over HTTPS (see `includes/bootstrap.php`).
- Sessions expire after `session_lifetime_minutes` of inactivity.

## Password recovery (secret question/answer)

Security questions are inherently weaker than modern recovery methods, so the
flow in `includes/auth.php` is defensive by design:

- The secret answer is normalized (trimmed, lowercased, collapsed whitespace)
  and stored only as a `password_hash()` — never in plain text.
- Every step (question requested, answer checked, password reset) is rate
  limited per email and per IP, and logged to `password_recovery_attempts`.
- The flow never reveals whether an email address has an account: unknown
  emails are shown a generic recovery question and always fail the answer
  check with the same generic message as a wrong answer.
- A session-bound, expiring token (`recovery_token_ttl_minutes`) links the
  three steps together; it can't be replayed after expiry or from a
  different session.
- Minimum answer length is enforced at registration and change time.

## Authorization

- Roles (`administrator`, `project_manager`, `employee`, `viewer`) are
  enforced in `includes/permissions.php` and re-checked in every `api/*.php`
  handler and page — the UI hiding a button is never the only gate.
- Project-level visibility (`can_view_project`) and edit rights
  (`can_edit_activity`, `can_manage_project`) are checked against ownership
  and project membership before any read/write of scoped data, preventing
  insecure direct object references (e.g. guessing another team's activity
  ID).
- Reclassifying an activity between planned/unplanned requires a role check,
  a reason, and is always written to `audit_logs` with the prior value
  retained.

## Data access

- All database access goes through the PDO wrapper in `includes/db.php`
  (`PDO::ATTR_EMULATE_PREPARES = false`) and every query in
  `includes/models/*.php` and `api/*.php` uses prepared statements with bound
  parameters — no string-concatenated SQL.
- Output is escaped with `e()` (a `htmlspecialchars` wrapper) everywhere
  user-supplied data is echoed into HTML, mitigating stored/reflected XSS.
- CSRF tokens (`includes/csrf.php`) are required on every state-changing POST
  request, both from server-rendered forms and JSON API calls
  (`X-CSRF-Token` header).
- Database and application errors are caught and logged server-side
  (`error_log`); raw exception messages, SQL text, stack traces, password
  hashes, and recovery-answer hashes are never sent to the client. Unhandled
  DB connection failures render the generic `500.php` page.
- `config/`, `includes/`, and `database/` are denied by `.htaccess` in case
  the document root is ever pointed above this folder.

## Audit trail

`audit_logs` records entity type/ID, action, previous and new values (JSON),
acting user, IP address, and timestamp for activity, project, person, time
entry, project membership, and account-recovery changes. Users can view the
history of entities they're authorized to access from within the relevant
page; administrators can view the full log at `audit_log.php`.

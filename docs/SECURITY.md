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

## File uploads (profile photos, pasted description images)

There are two user-controlled file upload paths in the app, and they share
the same defensive shape:

- The profile photo on `profile.php` — see `includes/models/avatars.php`.
- Images pasted into a task description's rich text editor (Edit Activity
  dialog) — see `includes/models/description_images.php` and the
  `upload_description_image` action in `api/activities.php`. Unlike the
  avatar upload, this isn't scoped to a specific record (a new task doesn't
  have an id yet when its description is being drafted), so it's reachable
  by any logged-in user, the same trust level as creating a task or leaving
  a comment.

Both follow the same pattern:

- The upload is never trusted as-is: `getimagesize()` validates it's
  actually a decodable image (not just a renamed file), and it's re-encoded
  from scratch through GD (`imagecreatefromstring()` → resample →
  `imagejpeg()`/`imagepng()`) before being written to disk — nothing about
  the original file's bytes, metadata, or embedded content survives into the
  stored copy.
- Stored filenames are always server-generated (`random_bytes()`-based), never
  derived from the original filename, so path traversal and filename
  collisions aren't possible.
- `uploads/avatars/` and `uploads/description_images/` are plain
  publicly-readable folders (visible to any logged-in user by design) but
  each has its own `.htaccess` denying script execution, so even a
  hypothetical malformed file saved there can't be run as PHP.
- Uploads are capped (5MB raw for avatars, 10MB for pasted images, before
  processing) and MIME-checked against an allow-list (JPEG/PNG/GIF/WEBP)
  before any processing happens.

Pasted description images have one more layer, since (unlike an avatar) the
resulting `<img>` tag ends up embedded directly in rich text HTML that
`sanitize_html()` (`includes/functions.php`) has to let through on every
future save: an `<img src>` is kept only if it starts with this app's own
`base_url('uploads/description_images/')` prefix followed by exactly a
generated filename (32 hex characters + `.jpg`/`.png`, nothing else) — every
other attribute (`style`, `srcset`, `onerror`, ...) is stripped, and `width`
is kept only as a bare number in a plausible range. An `<img>` that fails
that check is dropped entirely rather than left half-sanitized. This is what
stops a crafted request from embedding an arbitrary external image, a
tracking pixel, or a `javascript:`/`data:` URL disguised as a pasted image.
Cleanup of the underlying files (when an image is removed from a
description, or the whole task is deleted) is best-effort, not exhaustively
tracked — see the docblock in `description_images.php` for the reasoning.

## Supporting document attachments (projects & tasks)

Projects and tasks can each have multiple uploaded files attached ("Supporting
documents" in the Edit Project dialog and the Edit Activity dialog's
Attachments tab) — see `includes/models/attachments.php` and
`api/attachments.php`. Unlike avatars/description images, these are NOT
public-by-design: a restricted Employee who can't see a project shouldn't be
able to fetch its files either, so this feature departs from the
avatar/description-image pattern in two important ways:

- Files are stored under `storage/attachments/`, a folder outside the public
  `uploads/` tree entirely, whose own `.htaccess` denies ALL direct web
  access (`Require all denied`), not just script execution.
- There is no public URL for a stored file at all. Every download goes
  through `api/attachments.php`'s `download` action, which loads the
  attachment's parent project or task and re-checks `can_view_project()` /
  `activity_is_visible()` — the exact same visibility rules enforced
  everywhere else in the app — before streaming a single byte. Upload and
  delete are gated the same way on `can_manage_project()` /
  `can_edit_activity()`.

File-type validation is a strict allow-list (never a block-list): common
office/document formats (PDF, Word, Excel, PowerPoint, OpenDocument, RTF,
TXT, CSV) and common image formats only — nothing that could plausibly be
executed by a server or rendered as active content by a browser (no
`.php`/`.js`/`.html`/`.svg`/`.exe`/generic `.zip`/etc). An upload is accepted
only if both its extension is on the allow-list AND its fileinfo-sniffed
content type matches an acceptable value for that extension (see
`AF_ATTACHMENT_ALLOWED_EXTENSIONS` in `attachments.php` for the full map and
the reasoning behind the looser MIME tolerance given to zip-based Office/
OpenDocument formats and legacy OLE binary formats). Stored filenames are
always server-generated (`random_bytes()`-based), never derived from the
original filename. Uploads are capped at 25MB per file, with a defensive cap
on the number of files per request.

On download, the original (uploader-supplied) filename is used only as an
advisory suggested filename — stripped of characters that could break out of
the `Content-Disposition` header — and the response always forces a download
(`Content-Disposition: attachment`) with `X-Content-Type-Options: nosniff`,
so even a file that somehow slipped past the allow-list is never rendered
inline in the app's own origin.

Deleting a project or a task deletes its attachments (files and rows) as
part of the same operation — see `delete_attachments_for_entity()` and its
call sites in `delete_project()` / `delete_activity()`.

## Audit trail

`audit_logs` records entity type/ID, action, previous and new values (JSON),
acting user, IP address, and timestamp for activity, project, person, time
entry, project membership, and account-recovery changes. Users can view the
history of entities they're authorized to access from within the relevant
page; administrators can view the full log at `audit_log.php`.

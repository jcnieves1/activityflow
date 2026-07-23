# ActivityFlow

ActivityFlow tracks how employees' time is divided between planned daily
activities, planned project tasks, unplanned/last-minute requests, and ad-hoc
work — and shows who requested unplanned work, how it affected projects and
workload, and how each employee's day actually unfolded versus how it was
planned.

Built with plain HTML5/CSS3/vanilla JavaScript, PHP 8.2+, MySQL 8+, Bootstrap
5, Chart.js, and FullCalendar. No PHP framework — a conventional folder
structure that runs on any Apache/PHP/MySQL stack (XAMPP, WAMP, LAMP, or
shared hosting).

## Feature summary

- Role-based access: Administrator, Project Manager, Employee, Viewer/Auditor
  — enforced server-side on every page and API endpoint, not just hidden in
  the UI.
- Registration with a secret question/answer recovery flow (no email
  verification required), rate-limited and audit-logged.
- People/requester directory independent of login accounts, with
  duplicate-warning on creation.
- Projects with members, roles, duration-weighted (default) or simple-count
  progress calculation.
- A single central `activities` table modeling planned work, unplanned
  requests, and ad-hoc tasks, with full requester/requested-time/created-time/
  scheduled-time/actual-time tracking and an audit trail for reclassification.
- My Day workspace, drag-and-drop Calendar (FullCalendar), and a Timeline /
  time-lapse view that replays how a day's plan changed as unplanned work
  arrived.
- Personal and Manager dashboards (Chart.js) and a Reports Center covering the
  20 required reports plus a dedicated Requester Analytics page.
- Notifications, audit log, and an admin panel for users, roles, categories,
  and departments.

## Folder structure

```
config/            Configuration (config.php is git-ignored; copy from config.sample.php)
includes/          PHP application code (bootstrap, auth, permissions, models, templates)
includes/models/   Function-based data-access layer (PDO prepared statements only)
api/               JSON endpoints consumed by the front-end JavaScript
admin/             Administrator-only pages
assets/css, assets/js   Front-end assets
database/          schema.sql, seed.sql, seed_users.php
docs/              This documentation
*.php (root)       Server-rendered pages (dashboard, my_day, calendar, reports, …)
```

See `docs/INSTALL.md` to get running, `docs/SECURITY.md` for the security
model, and `docs/TEST_CHECKLIST.md` for a manual verification checklist
mapped to the acceptance criteria.

## Demo accounts

After running `database/seed_users.php` (see INSTALL.md), you can log in with
any of the accounts listed there. **These are development-only credentials —
change or remove them before using ActivityFlow with real data.**

## Known simplifications

- PDF export in the Reports Center uses the browser's print dialog ("Print /
  PDF"). If you install a PHP PDF library (e.g. Dompdf), `api/reports.php`
  can be extended to render PDFs server-side — see the comment there.
- Recurring planned activities create independent occurrences (not a linked
  recurrence series); editing one occurrence does not move the others.
- The daily capacity shown on My Day assumes a fixed 8-hour day; this can be
  made per-person if needed.

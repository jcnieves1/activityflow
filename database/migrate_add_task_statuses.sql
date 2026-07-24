-- Adds the admin-manageable task_statuses table and converts activities.status
-- from a fixed ENUM to a free-text key into it, so administrators can rename,
-- add, and remove statuses at runtime (see admin/statuses.php).
-- Run this against an existing database that predates this migration.

CREATE TABLE task_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_task_status_slug (slug)
) ENGINE=InnoDB;

INSERT INTO task_statuses (slug, label, sort_order, is_system) VALUES
 ('backlog', 'Backlog', 10, 0),
 ('planned', 'Planned', 20, 1),
 ('ready', 'Ready', 30, 0),
 ('in_progress', 'In Progress', 40, 1),
 ('blocked', 'Blocked', 50, 0),
 ('waiting', 'Waiting', 60, 0),
 ('completed', 'Completed', 70, 1),
 ('cancelled', 'Cancelled', 80, 1);

-- Existing rows already only ever contain these 8 values (the old ENUM's
-- domain), so this widens the column without needing to rewrite any data.
ALTER TABLE activities MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'backlog';

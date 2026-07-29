-- Adds an "Is this an Issue?" flag to tasks, so any task (planned or
-- unplanned, in any project) can be tagged as an issue and filtered on
-- across the task list views. Defaults to 0 (not an issue) for both new
-- rows and every existing row.
-- Run this against an existing database that predates this migration.

ALTER TABLE activities ADD COLUMN is_issue TINYINT(1) NOT NULL DEFAULT 0 AFTER is_milestone;

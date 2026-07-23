-- Run this once against your existing ActivityFlow database to enable comment
-- editing (adds a column that tracks when a comment was last edited).
--
--   mysql -u root -p activityflow < database/migrate_add_comment_editing.sql
--
-- Safe to run on a fresh install too — schema.sql already includes this column
-- for new databases, so this file only matters for databases created before
-- comment editing was added.

ALTER TABLE activity_comments
    ADD COLUMN updated_at DATETIME NULL DEFAULT NULL AFTER created_at;

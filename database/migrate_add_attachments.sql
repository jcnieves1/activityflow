-- Adds the Supporting Documents (attachments) feature: multi-file uploads
-- attached to a project or a task, downloadable through a permission-checked
-- endpoint rather than a public URL. See includes/models/attachments.php and
-- api/attachments.php.
-- Run this against an existing database that predates this migration.
--
-- After running this, also create the storage/attachments/ folder (writable
-- by the web server user) with a deny-all .htaccess — see
-- storage/attachments/.htaccess in a fresh checkout for the exact rules, and
-- docs/INSTALL.md for the writable-folders note.

CREATE TABLE attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('project','activity') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(64) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attachments_entity (entity_type, entity_id),
    KEY idx_attachments_uploaded_by (uploaded_by),
    CONSTRAINT fk_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

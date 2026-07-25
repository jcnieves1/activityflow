-- Adds the admin-manageable request_channels table and converts
-- activities.request_channel from a fixed ENUM to a free-text key into it,
-- so administrators can rename, add, and remove channels at runtime (see
-- admin/request_channels.php).
-- Run this against an existing database that predates this migration.

CREATE TABLE request_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_channel_slug (slug)
) ENGINE=InnoDB;

INSERT INTO request_channels (slug, label, sort_order, is_system) VALUES
 ('manager_request', 'Manager Request', 10, 0),
 ('coworker_request', 'Coworker Request', 20, 0),
 ('customer_request', 'Customer Request', 30, 0),
 ('meeting', 'Meeting', 40, 0),
 ('chat', 'Chat', 50, 0),
 ('phone', 'Phone', 60, 0),
 ('walk_up', 'Walk-up', 70, 0),
 ('system_incident', 'System Incident', 80, 0),
 ('self_initiated', 'Self-initiated', 90, 0),
 ('other', 'Other', 100, 0);

-- Existing rows already only ever contain these 10 values (the old ENUM's
-- domain), so this widens the column without needing to rewrite any data.
ALTER TABLE activities MODIFY COLUMN request_channel VARCHAR(40) DEFAULT NULL;

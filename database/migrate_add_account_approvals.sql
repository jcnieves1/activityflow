-- Adds the account approval workflow: a self-registered account can no
-- longer log in until an administrator approves it. See
-- includes/models/account_approvals.php, api/account_approvals.php, and
-- admin/account_approvals.php.
-- Run this against an existing database that predates this migration.

-- Existing rows are all 'active'/'inactive'/'locked' already (the old ENUM's
-- domain), so widening it is safe and doesn't touch any existing data —
-- accounts created before this migration keep working exactly as before.
-- New self-registrations from here on start as 'pending_approval' instead of
-- 'active' (see register_user() in includes/auth.php).
ALTER TABLE users
    MODIFY COLUMN status ENUM('pending_approval','active','inactive','locked','rejected') NOT NULL DEFAULT 'active';

CREATE TABLE account_approval_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action ENUM('approved','rejected') NOT NULL,
    reason TEXT DEFAULT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aaa_user (user_id, created_at),
    KEY idx_aaa_actor (actor_user_id),
    KEY idx_aaa_created (created_at),
    CONSTRAINT fk_aaa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_aaa_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

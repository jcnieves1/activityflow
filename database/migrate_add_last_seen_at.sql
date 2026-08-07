-- Adds presence tracking for the "Online (x)" topbar widget — see
-- includes/models/presence.php. Run this against an existing database that
-- predates the feature.

ALTER TABLE users
    ADD COLUMN last_seen_at DATETIME DEFAULT NULL AFTER locale,
    ADD KEY idx_users_last_seen (last_seen_at);

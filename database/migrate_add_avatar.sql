-- Adds profile picture support — see includes/models/avatars.php. Run this
-- against an existing database that predates the feature.

ALTER TABLE people
    ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER user_id;

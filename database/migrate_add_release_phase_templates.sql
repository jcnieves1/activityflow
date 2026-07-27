-- Adds the admin-manageable release_phase_templates table (Administration →
-- Release Phase Templates): the ordered list of default phase names applied
-- whenever a new Release is created — see
-- includes/models/release_phase_templates.php and
-- includes/models/releases.php's generate_default_phases(). Run this against
-- an existing database that already has the Releases feature
-- (migrate_add_releases.sql) but predates this migration.

CREATE TABLE release_phase_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_release_phase_template_name (name)
) ENGINE=InnoDB;

INSERT INTO release_phase_templates (name, sort_order) VALUES
 ('Grooming and BRD', 10),
 ('FDS and TDS', 20),
 ('Scope Commit', 30),
 ('Build', 40),
 ('SIT', 50),
 ('UAT and L&P', 60),
 ('Code Freeze', 70),
 ('MTP', 80);

-- Existing releases' already-created phases (release_phases) are untouched —
-- this table only affects releases created from now on.

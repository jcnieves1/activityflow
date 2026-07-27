-- Adds the Releases feature: a release is a company launch made up of several
-- project executions, with Design/Build/UAT/MTP phases and an optional set of
-- associated projects. See includes/models/releases.php and
-- admin/releases.php / admin/release_detail.php.
-- Run this against an existing database that predates this migration.

CREATE TABLE releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_releases_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE release_phases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_release_phases_release (release_id),
    CONSTRAINT fk_release_phases_release FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE projects
    ADD COLUMN release_id INT UNSIGNED DEFAULT NULL AFTER department_id,
    ADD CONSTRAINT fk_projects_release FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE SET NULL;

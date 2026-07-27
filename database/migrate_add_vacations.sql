-- Adds the Vacations feature: people can log their own consecutive days off
-- (one row per consecutive block), visible to everyone via the Vacations
-- page/calendar, and tasks scheduled during a person's vacation are flagged
-- with a warning elsewhere in the app. See includes/models/vacations.php,
-- can_manage_vacation() in includes/permissions.php, and vacations.php.
-- Run this against an existing database that predates this migration.

CREATE TABLE vacations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vacations_person (person_id),
    KEY idx_vacations_dates (start_date, end_date),
    CONSTRAINT fk_vacations_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_vacations_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

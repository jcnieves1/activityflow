-- Adds Task Templates: reusable, admin/PM-managed sets of predefined tasks
-- that can be applied to any project so repetitive/recurring project setup
-- tasks don't have to be typed in by hand every time.
-- Run this against an existing database that predates this migration.

CREATE TABLE task_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    description TEXT,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_templates_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE task_template_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    title VARCHAR(220) NOT NULL,
    description TEXT,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    estimated_minutes INT UNSIGNED DEFAULT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    is_milestone TINYINT(1) NOT NULL DEFAULT 0,
    is_issue TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_task_template_items_template (template_id),
    CONSTRAINT fk_tti_template FOREIGN KEY (template_id) REFERENCES task_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_tti_category FOREIGN KEY (category_id) REFERENCES activity_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ActivityFlow — MySQL 8 schema
-- All tables InnoDB, utf8mb4. Run this before seed.sql.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS activityflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE activityflow;

-- ---------------------------------------------------------------------
-- Reference / directory tables
-- ---------------------------------------------------------------------

CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    secret_question VARCHAR(255) NOT NULL,
    secret_answer_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    theme ENUM('golden','green','blue') NOT NULL DEFAULT 'golden',
    locale ENUM('en','es') NOT NULL DEFAULT 'en',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_user_role (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE people (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    job_title VARCHAR(150) DEFAULT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    organization VARCHAR(150) DEFAULT NULL,
    org_role VARCHAR(120) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    manager_id INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT,
    user_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_people_name (full_name),
    KEY idx_people_email (email),
    KEY idx_people_department (department_id),
    CONSTRAINT fk_people_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_people_manager FOREIGN KEY (manager_id) REFERENCES people(id) ON DELETE SET NULL,
    CONSTRAINT fk_people_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_people_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Projects
-- ---------------------------------------------------------------------

CREATE TABLE projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    code VARCHAR(30) NOT NULL,
    description TEXT,
    owner_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    target_completion_date DATE DEFAULT NULL,
    actual_completion_date DATE DEFAULT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('draft','not_started','active','on_hold','completed','cancelled','archived') NOT NULL DEFAULT 'draft',
    planned_effort_hours DECIMAL(10,2) DEFAULT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#4361ee',
    notes TEXT,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_projects_code (code),
    KEY idx_projects_status (status),
    KEY idx_projects_owner (owner_id),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES people(id) ON DELETE RESTRICT,
    CONSTRAINT fk_projects_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE project_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    person_id INT UNSIGNED NOT NULL,
    project_role ENUM('project_manager','contributor','reviewer','stakeholder','viewer') NOT NULL DEFAULT 'contributor',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_member (project_id, person_id),
    CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Classification
-- ---------------------------------------------------------------------

CREATE TABLE activity_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB;

-- Admin-manageable task/activity statuses. `slug` is the stable internal key
-- stored on activities.status and referenced by business logic (see
-- includes/models/task_statuses.php); `label` is the admin-editable display
-- text. `is_system` marks the 4 statuses (planned, in_progress, completed,
-- cancelled) that create/update/progress logic structurally depends on —
-- their label can be renamed but the row itself cannot be deleted. The other
-- defaults (backlog, ready, blocked, waiting) and any admin-added statuses
-- can be freely deleted, reassigning any activities that use them first.
CREATE TABLE task_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_task_status_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_tag_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Activities (central table)
-- ---------------------------------------------------------------------

CREATE TABLE activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(220) NOT NULL,
    description TEXT,
    activity_type ENUM('planned','unplanned') NOT NULL,
    is_adhoc TINYINT(1) NOT NULL DEFAULT 0,
    project_id INT UNSIGNED DEFAULT NULL,
    parent_activity_id INT UNSIGNED DEFAULT NULL,
    assignee_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    requested_at DATETIME DEFAULT NULL,
    planned_start_at DATETIME DEFAULT NULL,
    target_completion_at DATETIME DEFAULT NULL,
    actual_start_at DATETIME DEFAULT NULL,
    actual_completion_at DATETIME DEFAULT NULL,
    estimated_minutes INT UNSIGNED DEFAULT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    -- Free-text key into task_statuses.slug rather than an ENUM, so
    -- administrators can add/rename/remove statuses at runtime (see
    -- admin/statuses.php) without a schema migration for every change.
    status VARCHAR(40) NOT NULL DEFAULT 'backlog',
    completion_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
    category_id INT UNSIGNED DEFAULT NULL,
    interruption_reason VARCHAR(255) DEFAULT NULL,
    request_channel ENUM('manager_request','coworker_request','customer_request','meeting','chat','phone','walk_up','system_incident','self_initiated','other') DEFAULT NULL,
    notes TEXT,
    original_classification ENUM('planned','unplanned') DEFAULT NULL,
    reclassified_at DATETIME DEFAULT NULL,
    reclassified_by INT UNSIGNED DEFAULT NULL,
    reclassification_reason TEXT,
    is_milestone TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_activities_project (project_id),
    KEY idx_activities_assignee (assignee_id),
    KEY idx_activities_requester (requester_id),
    KEY idx_activities_status (status),
    KEY idx_activities_type (activity_type),
    KEY idx_activities_planned_start (planned_start_at),
    KEY idx_activities_requested_at (requested_at),
    KEY idx_activities_parent (parent_activity_id),
    CONSTRAINT fk_activities_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_activities_parent FOREIGN KEY (parent_activity_id) REFERENCES activities(id) ON DELETE SET NULL,
    CONSTRAINT fk_activities_assignee FOREIGN KEY (assignee_id) REFERENCES people(id) ON DELETE RESTRICT,
    CONSTRAINT fk_activities_requester FOREIGN KEY (requester_id) REFERENCES people(id) ON DELETE RESTRICT,
    CONSTRAINT fk_activities_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_activities_category FOREIGN KEY (category_id) REFERENCES activity_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_activities_reclassified_by FOREIGN KEY (reclassified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_tags (
    activity_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (activity_id, tag_id),
    CONSTRAINT fk_at_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_at_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_schedule_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL,
    old_planned_start_at DATETIME DEFAULT NULL,
    old_target_completion_at DATETIME DEFAULT NULL,
    new_planned_start_at DATETIME DEFAULT NULL,
    new_target_completion_at DATETIME DEFAULT NULL,
    changed_by INT UNSIGNED DEFAULT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ash_activity (activity_id),
    CONSTRAINT fk_ash_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_ash_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_dependencies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL,
    depends_on_activity_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dependency (activity_id, depends_on_activity_id),
    CONSTRAINT fk_dep_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_dep_depends_on FOREIGN KEY (depends_on_activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    KEY idx_comments_activity (activity_id),
    CONSTRAINT fk_comments_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Time tracking / interruptions
-- ---------------------------------------------------------------------

CREATE TABLE time_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL,
    person_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME DEFAULT NULL,
    duration_minutes INT UNSIGNED DEFAULT NULL,
    is_manual TINYINT(1) NOT NULL DEFAULT 0,
    is_timer TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_te_activity (activity_id),
    KEY idx_te_person (person_id),
    KEY idx_te_started (started_at),
    CONSTRAINT fk_te_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_te_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE RESTRICT,
    CONSTRAINT chk_te_duration CHECK (duration_minutes IS NULL OR duration_minutes >= 0)
) ENGINE=InnoDB;

CREATE TABLE interruptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interrupting_activity_id INT UNSIGNED NOT NULL,
    interrupted_activity_id INT UNSIGNED DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    ended_at DATETIME DEFAULT NULL,
    time_lost_minutes INT UNSIGNED DEFAULT NULL,
    was_resumed TINYINT(1) DEFAULT NULL,
    impact_on_target_date VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_int_interrupting (interrupting_activity_id),
    KEY idx_int_interrupted (interrupted_activity_id),
    CONSTRAINT fk_int_interrupting FOREIGN KEY (interrupting_activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_int_interrupted FOREIGN KEY (interrupted_activity_id) REFERENCES activities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notifications
-- ---------------------------------------------------------------------

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(500) DEFAULT NULL,
    related_entity_type VARCHAR(60) DEFAULT NULL,
    related_entity_id INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_recipient (recipient_user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Security / audit
-- ---------------------------------------------------------------------

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_la_email_time (email, created_at),
    KEY idx_la_ip_time (ip_address, created_at)
) ENGINE=InnoDB;

CREATE TABLE password_recovery_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    step VARCHAR(40) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pra_email_time (email, created_at),
    KEY idx_pra_ip_time (ip_address, created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    old_value JSON DEFAULT NULL,
    new_value JSON DEFAULT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ActivityFlow — demo/sample data
-- Run AFTER schema.sql. Run database/seed_users.php afterwards to create
-- login accounts (passwords are hashed with PHP's password_hash(), not stored here).

USE activityflow;

-- Roles ------------------------------------------------------------
INSERT INTO roles (name, description) VALUES
 ('administrator', 'Full system access'),
 ('project_manager', 'Manages projects, tasks and project reports'),
 ('employee', 'Plans and logs personal and assigned work'),
 ('viewer', 'Read-only access to permitted dashboards and reports');

-- Departments --------------------------------------------------------
INSERT INTO departments (name) VALUES
 ('Engineering'), ('Customer Support'), ('Product'), ('Sales'), ('Operations');

-- Activity categories --------------------------------------------------
INSERT INTO activity_categories (name, description) VALUES
 ('Development', 'Coding and engineering work'),
 ('Support', 'Customer or internal support requests'),
 ('Meeting', 'Meetings and calls'),
 ('Admin', 'Administrative and operational tasks'),
 ('Planning', 'Planning and coordination'),
 ('Incident', 'Production or system incidents'),
 ('Documentation', 'Writing or updating documentation');

-- Tags -----------------------------------------------------------------
INSERT INTO tags (name) VALUES ('billing'),('bug'),('urgent-fix'),('followup'),('research'),('release');

-- Task statuses ----------------------------------------------------------
-- is_system=1 (planned, in_progress, completed, cancelled) protects the 4
-- statuses create/update/progress logic depends on from deletion; see
-- includes/models/task_statuses.php.
INSERT INTO task_statuses (slug, label, sort_order, is_system) VALUES
 ('backlog', 'Backlog', 10, 0),
 ('planned', 'Planned', 20, 1),
 ('ready', 'Ready', 30, 0),
 ('in_progress', 'In Progress', 40, 1),
 ('blocked', 'Blocked', 50, 0),
 ('waiting', 'Waiting', 60, 0),
 ('completed', 'Completed', 70, 1),
 ('cancelled', 'Cancelled', 80, 1);

-- Request channels --------------------------------------------------------
-- No business logic keys off a specific slug, so every default channel is
-- is_system=0 (freely renamable/deletable); see includes/models/request_channels.php.
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

-- Release phase templates -------------------------------------------------
-- Applied, in this order, whenever a new release is created; see
-- includes/models/release_phase_templates.php and admin/release_phase_templates.php.
INSERT INTO release_phase_templates (name, sort_order) VALUES
 ('Grooming and BRD', 10),
 ('FDS and TDS', 20),
 ('Scope Commit', 30),
 ('Build', 40),
 ('SIT', 50),
 ('UAT and L&P', 60),
 ('Code Freeze', 70),
 ('MTP', 80);

-- People (requesters / employees / stakeholders) ------------------------
-- department_id: 1 Engineering, 2 Support, 3 Product, 4 Sales, 5 Operations
INSERT INTO people (id, full_name, job_title, department_id, organization, org_role, email, phone, manager_id, is_active, notes) VALUES
 (1,  'Alicia Moreno',  'IT Administrator',       1, 'ActivityFlow Inc.', 'Administrator', 'alicia.moreno@activityflow.test',  '555-0101', NULL, 1, 'System administrator'),
 (2,  'Ben Ortiz',       'Engineering Manager',    1, 'ActivityFlow Inc.', 'Project Manager', 'ben.ortiz@activityflow.test',    '555-0102', NULL, 1, 'Manages the Platform project'),
 (3,  'Carla Diaz',      'Software Engineer',      1, 'ActivityFlow Inc.', 'Employee', 'carla.diaz@activityflow.test',        '555-0103', 2,    1, NULL),
 (4,  'David Kim',       'Software Engineer',      1, 'ActivityFlow Inc.', 'Employee', 'david.kim@activityflow.test',         '555-0104', 2,    1, NULL),
 (5,  'Elena Petrova',   'Support Lead',           2, 'ActivityFlow Inc.', 'Project Manager', 'elena.petrova@activityflow.test', '555-0105', NULL, 1, 'Manages the Support Backlog project'),
 (6,  'Frank Lucas',     'Support Specialist',     2, 'ActivityFlow Inc.', 'Employee', 'frank.lucas@activityflow.test',       '555-0106', 5,    1, NULL),
 (7,  'Grace Han',       'Product Manager',        3, 'ActivityFlow Inc.', 'Viewer', 'grace.han@activityflow.test',           '555-0107', NULL, 1, NULL),
 (8,  'Hassan Ali',      'Account Executive',      4, 'ActivityFlow Inc.', 'Requester', 'hassan.ali@activityflow.test',       '555-0108', NULL, 1, 'Frequently requests customer fixes'),
 (9,  'Irene Novak',     'Operations Coordinator', 5, 'ActivityFlow Inc.', 'Requester', 'irene.novak@activityflow.test',      '555-0109', NULL, 1, NULL),
 (10, 'Contoso Customer Success', 'External Contact', NULL, 'Contoso Ltd.', 'Customer', 'success@contoso.example', NULL, NULL, 1, 'External customer requester, no login account');

-- Users (login accounts) are created by database/seed_users.php, which also
-- sets people.user_id for rows 1-6 above and assigns roles.

-- Projects ---------------------------------------------------------------
INSERT INTO projects (id, name, code, description, owner_id, department_id, start_date, target_completion_date, priority, status, planned_effort_hours, color, created_by) VALUES
 (1, 'Platform Modernization', 'PLAT-01', 'Backend platform rewrite and infrastructure upgrade.', 2, 1, '2026-05-01', '2026-09-30', 'high', 'active', 480.00, '#4361ee', NULL),
 (2, 'Support Backlog Q3', 'SUP-Q3', 'Ongoing customer support ticket backlog for Q3.', 5, 2, '2026-07-01', '2026-09-30', 'normal', 'active', 200.00, '#f4a261', NULL),
 (3, 'Website Refresh', 'WEB-02', 'Marketing website redesign.', 7, 3, '2026-06-01', '2026-08-15', 'normal', 'on_hold', 120.00, '#2a9d8f', NULL);

INSERT INTO project_members (project_id, person_id, project_role) VALUES
 (1, 2, 'project_manager'), (1, 3, 'contributor'), (1, 4, 'contributor'), (1, 7, 'stakeholder'),
 (2, 5, 'project_manager'), (2, 6, 'contributor'), (2, 8, 'stakeholder'),
 (3, 7, 'project_manager'), (3, 3, 'reviewer');

-- Planned activities -------------------------------------------------------
INSERT INTO activities
 (title, description, activity_type, is_adhoc, project_id, assignee_id, requester_id, requested_at, planned_start_at, target_completion_at, actual_start_at, actual_completion_at, estimated_minutes, priority, status, completion_pct, category_id, request_channel, original_classification)
VALUES
 ('Implement authentication service', 'Build the new PDO-based auth service with session regeneration.', 'planned', 0, 1, 3, 2, '2026-07-15 09:00:00', '2026-07-20 09:00:00', '2026-07-24 17:00:00', '2026-07-20 09:10:00', NULL, 480, 'high', 'in_progress', 55, 1, 'manager_request', 'planned'),
 ('Design database schema', 'Normalize schema for activities and time entries.', 'planned', 0, 1, 4, 2, '2026-07-10 09:00:00', '2026-07-18 09:00:00', '2026-07-19 17:00:00', '2026-07-18 09:05:00', '2026-07-19 16:40:00', 360, 'high', 'completed', 100, 1, 'manager_request', 'planned'),
 ('Weekly platform sync', 'Recurring project status meeting.', 'planned', 0, 1, 3, 2, '2026-07-01 09:00:00', '2026-07-22 09:00:00', '2026-07-22 09:30:00', NULL, NULL, 30, 'normal', 'planned', 0, 3, 'manager_request', 'planned'),
 ('Clear support ticket queue', 'Work through open Tier-1 tickets.', 'planned', 0, 2, 6, 5, '2026-07-20 08:00:00', '2026-07-22 08:00:00', '2026-07-22 12:00:00', '2026-07-22 08:05:00', NULL, 240, 'normal', 'in_progress', 40, 2, 'manager_request', 'planned'),
 ('Draft Q3 support metrics report', 'Prepare monthly metrics summary.', 'planned', 0, 2, 5, 5, '2026-07-18 09:00:00', '2026-07-23 09:00:00', '2026-07-23 15:00:00', NULL, NULL, 120, 'normal', 'planned', 0, 4, 'self_initiated', 'planned');

-- Unplanned / ad-hoc activities ---------------------------------------------
INSERT INTO activities
 (title, description, activity_type, is_adhoc, project_id, assignee_id, requester_id, requested_at, planned_start_at, target_completion_at, actual_start_at, actual_completion_at, estimated_minutes, priority, status, completion_pct, category_id, interruption_reason, request_channel, original_classification)
VALUES
 ('Fix production login bug', 'Users unable to log in after deploy; investigate and patch.', 'unplanned', 1, 1, 3, 1, '2026-07-22 10:15:00', '2026-07-22 10:20:00', '2026-07-22 12:00:00', '2026-07-22 10:22:00', '2026-07-22 11:40:00', 90, 'urgent', 'completed', 100, 6, 'Interrupted auth service work to fix live incident', 'system_incident', 'unplanned'),
 ('Pull customer usage numbers for Hassan', 'Ad-hoc data pull requested for a sales call.', 'unplanned', 1, NULL, 4, 8, '2026-07-22 09:40:00', NULL, '2026-07-22 13:00:00', NULL, NULL, 45, 'high', 'in_progress', 20, 4, 'Sales call in 2 hours, needs numbers now', 'chat', 'unplanned'),
 ('Investigate billing webhook failure', 'Contoso reported failing billing webhooks.', 'unplanned', 0, 2, 6, 10, '2026-07-22 08:30:00', '2026-07-22 08:45:00', '2026-07-22 11:00:00', '2026-07-22 08:50:00', NULL, 60, 'high', 'in_progress', 30, 6, 'Interrupted ticket queue work for customer-reported outage', 'customer_request', 'unplanned'),
 ('Restart Jenkins build agent', 'Build agent hung overnight, blocking all deploys.', 'unplanned', 1, NULL, 3, 1, '2026-07-22 07:55:00', '2026-07-22 08:00:00', '2026-07-22 08:30:00', '2026-07-22 08:00:00', '2026-07-22 08:15:00', 20, 'urgent', 'completed', 100, 6, NULL, 'walk_up', 'unplanned'),
 ('Answer procurement question', 'Operations asked about a software license renewal.', 'unplanned', 1, NULL, 5, 9, '2026-07-21 14:00:00', NULL, '2026-07-21 17:00:00', '2026-07-21 14:10:00', '2026-07-21 14:25:00', 15, 'low', 'completed', 100, 4, NULL, 'phone', 'unplanned');

-- Interruptions --------------------------------------------------------
INSERT INTO interruptions (interrupting_activity_id, interrupted_activity_id, started_at, ended_at, time_lost_minutes, was_resumed, impact_on_target_date, notes) VALUES
 (6, 1, '2026-07-22 10:20:00', '2026-07-22 11:40:00', 90, 1, 'None — auth task target date unaffected', 'Resumed auth service work immediately after the fix shipped.'),
 (8, 4, '2026-07-22 08:45:00', NULL, 30, 0, 'Ticket queue work paused, not yet resumed', 'Interrupted ticket queue work for customer-reported billing outage.');

-- Time entries ------------------------------------------------------------
INSERT INTO time_entries (activity_id, person_id, started_at, ended_at, duration_minutes, is_manual, is_timer, notes) VALUES
 (2, 4, '2026-07-18 09:05:00', '2026-07-18 12:00:00', 175, 0, 0, 'Schema drafting session'),
 (2, 4, '2026-07-19 09:00:00', '2026-07-19 16:40:00', 340, 0, 0, 'Finalized schema and reviewed with team'),
 (1, 3, '2026-07-20 09:10:00', '2026-07-20 12:30:00', 200, 0, 0, 'Auth service scaffolding'),
 (6, 3, '2026-07-22 10:22:00', '2026-07-22 11:40:00', 78, 0, 1, 'Live incident fix — login bug'),
 (4, 6, '2026-07-22 08:05:00', NULL, NULL, 0, 1, 'Working through ticket queue'),
 (9, 3, '2026-07-22 08:00:00', '2026-07-22 08:15:00', 15, 0, 1, 'Restarted Jenkins agent'),
 (8, 6, '2026-07-22 08:50:00', NULL, NULL, 0, 1, 'Investigating billing webhook');

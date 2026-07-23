-- Adds per-user color scheme and language preferences.
-- Run this against an existing database that predates this migration.

ALTER TABLE users
  ADD COLUMN theme ENUM('golden','green','blue') NOT NULL DEFAULT 'golden' AFTER last_login_at,
  ADD COLUMN locale ENUM('en','es') NOT NULL DEFAULT 'en' AFTER theme;

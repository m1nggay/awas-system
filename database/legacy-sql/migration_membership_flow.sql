-- =====================================================================
-- Migration: full membership application flow
-- ---------------------------------------------------------------------
-- Register -> email verification -> pending admin review -> approve ->
-- meter assignment -> active consumer account.
-- Run this once against an EXISTING awas_db database (after
-- migration_membership_applications.sql and migration_email_verification.sql).
-- Safe to re-run.
--
-- Applicants get a `users` row with the new role 'applicant' so they can
-- log in and see their application status, but requireLogin(['resident'])
-- keeps them out of the billing dashboard. On activation the role becomes
-- 'resident'.
-- =====================================================================

USE `awas_db`;

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('admin','staff','resident','applicant') NOT NULL DEFAULT 'resident';

-- Meter assignment details recorded when an application is activated.
ALTER TABLE `consumers`
  ADD COLUMN IF NOT EXISTS `meter_status` ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active' AFTER `meter_serial_number`,
  ADD COLUMN IF NOT EXISTS `initial_meter_reading` DECIMAL(10,2) DEFAULT NULL AFTER `meter_status`,
  ADD COLUMN IF NOT EXISTS `household_number` VARCHAR(30) DEFAULT NULL AFTER `initial_meter_reading`;

-- Widen the status set first (keeping the legacy 'pending'), migrate the
-- rows, then drop the legacy value.
ALTER TABLE `membership_applications`
  MODIFY COLUMN `status` ENUM('pending','pending_verification','pending_review','approved','active','rejected') NOT NULL DEFAULT 'pending_verification';

UPDATE `membership_applications` SET `status` = 'pending_review' WHERE `status` = 'pending';

ALTER TABLE `membership_applications`
  MODIFY COLUMN `status` ENUM('pending_verification','pending_review','approved','active','rejected') NOT NULL DEFAULT 'pending_verification'
    COMMENT 'pending_verification = email not yet verified; pending_review = waiting for admin; approved = waiting for meter assignment; active = consumer account created',
  MODIFY COLUMN `review_notes` VARCHAR(500) DEFAULT NULL COMMENT 'rejection reason shown to the applicant',
  ADD COLUMN IF NOT EXISTS `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'applicant login account' AFTER `reference_code`,
  ADD COLUMN IF NOT EXISTS `birth_date` DATE DEFAULT NULL AFTER `full_name`,
  ADD COLUMN IF NOT EXISTS `sex` ENUM('male','female') DEFAULT NULL AFTER `birth_date`,
  ADD COLUMN IF NOT EXISTS `barangay` VARCHAR(100) DEFAULT NULL AFTER `purok_id`,
  ADD COLUMN IF NOT EXISTS `municipality` VARCHAR(100) DEFAULT NULL AFTER `barangay`,
  ADD COLUMN IF NOT EXISTS `province` VARCHAR(100) DEFAULT NULL AFTER `municipality`,
  ADD COLUMN IF NOT EXISTS `household_number` VARCHAR(30) DEFAULT NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `household_members` SMALLINT UNSIGNED DEFAULT NULL AFTER `household_number`,
  ADD COLUMN IF NOT EXISTS `residence_type` ENUM('owned','rented','shared','other') DEFAULT NULL AFTER `household_members`,
  ADD COLUMN IF NOT EXISTS `id_type` VARCHAR(60) DEFAULT NULL AFTER `residence_type`,
  ADD COLUMN IF NOT EXISTS `id_file` VARCHAR(80) DEFAULT NULL COMMENT 'random file name inside storage/applications (never web-accessible)' AFTER `id_type`,
  ADD COLUMN IF NOT EXISTS `id_status` ENUM('not_submitted','submitted','verified','failed') NOT NULL DEFAULT 'not_submitted' AFTER `id_file`,
  ADD COLUMN IF NOT EXISTS `face_file` VARCHAR(80) DEFAULT NULL AFTER `id_status`,
  ADD COLUMN IF NOT EXISTS `face_status` ENUM('not_submitted','submitted','for_review','verified','failed') NOT NULL DEFAULT 'not_submitted' COMMENT 'set by an administrator after a manual look — no automated biometric matching' AFTER `face_file`,
  ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME DEFAULT NULL AFTER `face_status`,
  ADD COLUMN IF NOT EXISTS `submitted_at` DATETIME DEFAULT NULL COMMENT 'set once the email is verified and the application enters admin review' AFTER `email_verified_at`,
  ADD COLUMN IF NOT EXISTS `ip_address` VARCHAR(45) DEFAULT NULL AFTER `submitted_at`;

-- Index/constraint additions are not re-runnable with IF NOT EXISTS on every
-- server, so guard them via information_schema.
SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_applications' AND CONSTRAINT_NAME = 'fk_application_user');
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE `membership_applications` ADD CONSTRAINT `fk_application_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE INDEX IF NOT EXISTS `idx_application_email` ON `membership_applications` (`email`);
CREATE INDEX IF NOT EXISTS `idx_application_ip` ON `membership_applications` (`ip_address`, `created_at`);

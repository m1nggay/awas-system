-- =====================================================================
-- Migration: Public "Apply for Membership" flow
-- ---------------------------------------------------------------------
-- Run this once against an EXISTING awas_db database. Safe to re-run
-- (CREATE TABLE IF NOT EXISTS).
--
-- Lets someone WITHOUT an existing water account submit a request for
-- new service from the public site (apply_membership.php). Barangay
-- staff/admin review the request on admin/membership_applications.php:
-- approving it creates the actual `consumers` record (see
-- generateAccountNumber() in includes/functions.php), rejecting it just
-- records a reason. This is upstream of auth/register.php, which is for
-- someone who ALREADY has a consumer account and just wants an online
-- login for it.
-- =====================================================================

USE `awas_db`;

CREATE TABLE IF NOT EXISTS `membership_applications` (
  `application_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'e.g. APP-2026-0001 — shown to the applicant so they can ask about status',
  `full_name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `purok_id` INT UNSIGNED NOT NULL,
  `contact_number` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL COMMENT 'anything extra the applicant wanted the water office to know',
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `review_notes` VARCHAR(500) DEFAULT NULL COMMENT 'staff remarks, e.g. reason for rejection',
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `consumer_id` INT UNSIGNED DEFAULT NULL COMMENT 'set to the consumer record created on approval',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_application_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks`(`purok_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_application_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_application_consumer` FOREIGN KEY (`consumer_id`) REFERENCES `consumers`(`consumer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_application_status` (`status`),
  INDEX `idx_application_purok` (`purok_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

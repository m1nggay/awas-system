-- =====================================================================
-- Migration: Email verification for resident self-registration
-- ---------------------------------------------------------------------
-- Run this once against an EXISTING awas_db database. Safe to re-run
-- (CREATE TABLE IF NOT EXISTS; the ALTER TABLE is idempotent because it
-- just re-declares the same enum/default each time).
--
-- New self-registered residents (auth/register.php) are created with
-- status = 'pending' and cannot log in (see includes/auth.php
-- attemptLogin(), which already blocks any non-'active' status) until
-- they enter the 6-digit code emailed to them on auth/verify_email.php,
-- which flips their status to 'active'.
-- =====================================================================

USE `awas_db`;

ALTER TABLE `users`
  MODIFY COLUMN `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS `email_verification_otps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `otp_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of the 6-digit code — the plaintext code is never stored',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_email_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_email_otp_user` (`user_id`),
  INDEX `idx_email_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

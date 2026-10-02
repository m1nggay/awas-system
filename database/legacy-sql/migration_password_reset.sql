-- =====================================================================
-- Migration: "Forgot Password" email-OTP flow
-- ---------------------------------------------------------------------
-- Run this once against an EXISTING awas_db database. Safe to re-run
-- (CREATE TABLE IF NOT EXISTS).
-- =====================================================================

USE `awas_db`;

CREATE TABLE IF NOT EXISTS `password_reset_otps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `otp_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of the 6-digit code — the plaintext code is never stored',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_otp_user` (`user_id`),
  INDEX `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

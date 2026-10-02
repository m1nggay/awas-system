-- =====================================================================
-- AGAS: Smart Water Management and Billing System with Online Payment
-- Barangay Adlay
-- Database Schema (MySQL / phpMyAdmin compatible)
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `awas_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `awas_db`;

-- ---------------------------------------------------------------------
-- Table: users  (system accounts: admin, staff, resident)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('admin','staff','resident') NOT NULL DEFAULT 'resident',
  `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active' COMMENT 'pending = self-registered via auth/register.php, awaiting email verification',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: puroks (sub-village divisions of the barangay)
-- ---------------------------------------------------------------------
CREATE TABLE `puroks` (
  `purok_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purok_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: consumers (household water accounts)
-- ---------------------------------------------------------------------
CREATE TABLE `consumers` (
  `consumer_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `account_number` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'linked resident login account, nullable',
  `full_name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `purok_id` INT UNSIGNED NOT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `is_senior` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = senior citizen, gets the senior discount on bills',
  `meter_serial_number` VARCHAR(50) DEFAULT NULL,
  `connection_date` DATE DEFAULT NULL,
  `status` ENUM('active','disconnected','inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'staff/admin who registered consumer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_consumers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_consumers_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks`(`purok_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_consumers_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_consumers_name` (`full_name`),
  INDEX `idx_consumers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: billing_rates (configurable water rates, no code changes needed)
-- ---------------------------------------------------------------------
CREATE TABLE `billing_rates` (
  `rate_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rate_name` VARCHAR(100) NOT NULL,
  `min_consumption` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_consumption` DECIMAL(10,2) DEFAULT NULL COMMENT 'NULL = no upper limit',
  `rate_per_cubic_meter` DECIMAL(10,2) NOT NULL,
  `base_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'flat minimum charge for the bracket',
  `penalty_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'percentage penalty applied when overdue',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `effective_date` DATE NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rates_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_rates_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: meter_readings
-- ---------------------------------------------------------------------
CREATE TABLE `meter_readings` (
  `reading_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `consumer_id` INT UNSIGNED NOT NULL,
  `billing_period` VARCHAR(7) NOT NULL COMMENT 'format YYYY-MM',
  `previous_reading` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `current_reading` DECIMAL(10,2) NOT NULL,
  `consumption` DECIMAL(10,2) GENERATED ALWAYS AS (`current_reading` - `previous_reading`) STORED,
  `reading_date` DATE NOT NULL,
  `recorded_by` INT UNSIGNED NOT NULL COMMENT 'staff/admin user_id',
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reading_consumer` FOREIGN KEY (`consumer_id`) REFERENCES `consumers`(`consumer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reading_staff` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY `uq_consumer_period` (`consumer_id`, `billing_period`),
  INDEX `idx_reading_period` (`billing_period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: water_bills
-- ---------------------------------------------------------------------
CREATE TABLE `water_bills` (
  `bill_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bill_number` VARCHAR(30) NOT NULL UNIQUE,
  `consumer_id` INT UNSIGNED NOT NULL,
  `reading_id` INT UNSIGNED NOT NULL,
  `billing_period` VARCHAR(7) NOT NULL,
  `consumption` DECIMAL(10,2) NOT NULL,
  `rate_id` INT UNSIGNED DEFAULT NULL,
  `amount_due` DECIMAL(10,2) NOT NULL COMMENT 'sub-total: minimum charge + excess charge, before discount',
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'senior citizen discount',
  `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `bill_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `disconnection_date` DATE DEFAULT NULL,
  `status` ENUM('unpaid','partially_paid','paid','overdue') NOT NULL DEFAULT 'unpaid',
  `generated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bill_consumer` FOREIGN KEY (`consumer_id`) REFERENCES `consumers`(`consumer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_reading` FOREIGN KEY (`reading_id`) REFERENCES `meter_readings`(`reading_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_rate` FOREIGN KEY (`rate_id`) REFERENCES `billing_rates`(`rate_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_generator` FOREIGN KEY (`generated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_bill_status` (`status`),
  INDEX `idx_bill_period` (`billing_period`),
  INDEX `idx_bill_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: payments
-- ---------------------------------------------------------------------
CREATE TABLE `payments` (
  `payment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_reference` VARCHAR(40) NOT NULL UNIQUE,
  `bill_id` INT UNSIGNED NOT NULL,
  `consumer_id` INT UNSIGNED NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash','online','gcash','bank_transfer','paymongo','other') NOT NULL DEFAULT 'cash',
  `payment_gateway_txn_id` VARCHAR(100) DEFAULT NULL COMMENT 'external gateway reference only, never card/account numbers',
  `payment_date` DATETIME NOT NULL,
  `status` ENUM('pending','verified','failed','refunded') NOT NULL DEFAULT 'pending',
  `received_by` INT UNSIGNED DEFAULT NULL COMMENT 'staff/admin who recorded/verified, NULL for self-service online',
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `water_bills`(`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_consumer` FOREIGN KEY (`consumer_id`) REFERENCES `consumers`(`consumer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_staff` FOREIGN KEY (`received_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_payment_status` (`status`),
  INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: notifications
-- ---------------------------------------------------------------------
CREATE TABLE `notifications` (
  `notification_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'recipient (resident login account)',
  `bill_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `type` ENUM('bill_due','bill_overdue','payment_received','general') NOT NULL DEFAULT 'general',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_bill` FOREIGN KEY (`bill_id`) REFERENCES `water_bills`(`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: password_reset_otps ("Forgot Password" email OTP flow)
-- ---------------------------------------------------------------------
CREATE TABLE `password_reset_otps` (
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

-- ---------------------------------------------------------------------
-- Table: email_verification_otps (resident self-registration email
-- verification — see auth/register.php and auth/verify_email.php)
-- ---------------------------------------------------------------------
CREATE TABLE `email_verification_otps` (
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

-- ---------------------------------------------------------------------
-- Table: chatbot_faqs (AGAS Assistant knowledge base, managed by admins)
-- ---------------------------------------------------------------------
CREATE TABLE `chatbot_faqs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `category` ENUM('billing','meter_reading','payments','account','system','general') NOT NULL DEFAULT 'general',
  `keywords` VARCHAR(500) DEFAULT NULL COMMENT 'comma-separated keywords used for FAQ matching',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `hit_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'times matched by the chatbot, powers "commonly asked" reporting',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_faq_question` (`question`),
  CONSTRAINT `fk_faq_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_faq_status` (`status`),
  INDEX `idx_faq_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: chatbot_unanswered (questions the assistant couldn't answer,
-- logged so admins can grow the FAQ knowledge base)
-- ---------------------------------------------------------------------
CREATE TABLE `chatbot_unanswered` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question` VARCHAR(500) NOT NULL,
  `asked_by` INT UNSIGNED DEFAULT NULL COMMENT 'resident user_id, nullable if the account is later removed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_unanswered_user` FOREIGN KEY (`asked_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: system_settings (key-value config, e.g. due date offset, barangay info)
-- ---------------------------------------------------------------------
CREATE TABLE `system_settings` (
  `setting_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` VARCHAR(500) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: activity_logs (audit trail — supports security & maintainability)
-- ---------------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `details` VARCHAR(500) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: membership_applications (public "Apply for Membership" flow —
-- see apply_membership.php and admin/membership_applications.php.
-- Upstream of auth/register.php: this is for someone who does NOT yet
-- have a consumer account at all, requesting new water service.)
-- ---------------------------------------------------------------------
CREATE TABLE `membership_applications` (
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

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SAMPLE / SEED DATA
-- =====================================================================

-- Default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('barangay_name', 'Barangay Adlay', 'Name of the barangay for headers/receipts'),
('minimum_charge', '150', 'Minimum water charge in PHP, always billed'),
('minimum_cubic_meters', '10', 'Cubic meters covered by the minimum charge'),
('excess_rate_per_cubic_meter', '15', 'PHP per cubic meter beyond the minimum'),
('senior_discount_percent', '20', 'Discount percentage on the sub-total for senior citizens'),
('due_day_of_month', '19', 'Day of the month after the billing month on which the bill is due'),
('disconnection_days', '5', 'Days after the due date on which service is subject to disconnection'),
('overdue_grace_days', '5', 'Days after due_date before a bill is marked overdue and penalty applies'),
('currency_symbol', 'PHP', 'Currency label used in reports'),
('contact_email', 'agas.adlay@example.com', 'Support email shown to residents'),
('contact_number', '09171234567', 'Support contact number'),
('chatbot_ai_enabled', '0', 'Whether the AGAS Assistant chatbot may fall back to the AI API for questions the FAQ knowledge base cannot answer (requires an API key configured in config.php)');

-- Default puroks
INSERT INTO `puroks` (`purok_name`, `description`) VALUES
('Purok 1', NULL),
('Purok 2', NULL),
('Purok 3(Phase 2)', NULL),
('Purok 4(Phase 2)', NULL),
('Purok 4(Extension)', NULL),
('Purok 6', NULL),
('Purok 7', NULL);

-- Default users
-- password for ALL sample accounts below is: Password123!
-- hash is a genuine bcrypt hash (verified against PHP's password_verify() semantics —
-- the $2b$ and $2y$ bcrypt prefixes are interchangeable/compatible)
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `contact_number`, `role`, `status`) VALUES
('admin', '$2b$12$QXT10zEN3aOGpA0Xedzie.4Mo.5Pj4qfrO3tqqRuDvM7ZEG.DWwWm', 'Barangay AGAS Administrator', 'admin@agas-adlay.local', '09171000001', 'admin', 'active'),
('staff1', '$2b$12$QXT10zEN3aOGpA0Xedzie.4Mo.5Pj4qfrO3tqqRuDvM7ZEG.DWwWm', 'Juana Dela Cruz', 'staff1@agas-adlay.local', '09171000002', 'staff', 'active'),
('resident1', '$2b$12$QXT10zEN3aOGpA0Xedzie.4Mo.5Pj4qfrO3tqqRuDvM7ZEG.DWwWm', 'Pedro Santos', 'pedro.santos@example.com', '09171000003', 'resident', 'active'),
('resident2', '$2b$12$QXT10zEN3aOGpA0Xedzie.4Mo.5Pj4qfrO3tqqRuDvM7ZEG.DWwWm', 'Maria Reyes', 'maria.reyes@example.com', '09171000004', 'resident', 'active');

-- Billing rate brackets (tiered rate, effective now)
INSERT INTO `billing_rates` (`rate_name`, `min_consumption`, `max_consumption`, `rate_per_cubic_meter`, `base_charge`, `penalty_percentage`, `is_active`, `effective_date`, `created_by`) VALUES
('Minimum Charge (0-10 cu.m.)', 0.00, 10.00, 0.00, 100.00, 5.00, 1, '2026-01-01', 1),
('Tier 2 (11-20 cu.m.)', 10.01, 20.00, 18.00, 100.00, 5.00, 1, '2026-01-01', 1),
('Tier 3 (21-30 cu.m.)', 20.01, 30.00, 22.00, 100.00, 5.00, 1, '2026-01-01', 1),
('Tier 4 (31 cu.m. and above)', 30.01, NULL, 28.00, 100.00, 5.00, 1, '2026-01-01', 1);

-- Sample consumers
INSERT INTO `consumers` (`account_number`, `user_id`, `full_name`, `address`, `purok_id`, `contact_number`, `email`, `meter_serial_number`, `connection_date`, `status`, `created_by`) VALUES
('ADL-2026-0001', 3, 'Pedro Santos', 'Blk 2 Lot 5, Purok 1, Adlay', 1, '09171000003', 'pedro.santos@example.com', 'MTR-00123', '2024-03-15', 'active', 1),
('ADL-2026-0002', 4, 'Maria Reyes', 'Purok 3, Riverside St., Adlay', 3, '09171000004', 'maria.reyes@example.com', 'MTR-00124', '2024-05-20', 'active', 1),
('ADL-2026-0003', NULL, 'Roberto Garcia', 'Purok 2, Adlay', 2, '09179998887', NULL, 'MTR-00125', '2023-11-10', 'active', 1),
('ADL-2026-0004', NULL, 'Liza Fernandez', 'Purok 4, Upper Adlay', 4, '09179998886', NULL, 'MTR-00126', '2024-01-05', 'active', 1);

-- Sample meter readings (two consecutive billing periods for consumer 1 & 2)
INSERT INTO `meter_readings` (`consumer_id`, `billing_period`, `previous_reading`, `current_reading`, `reading_date`, `recorded_by`, `remarks`) VALUES
(1, '2026-07', 100.00, 112.00, '2026-07-28', 2, 'Normal reading'),
(1, '2026-08', 112.00, 125.00, '2026-08-28', 2, 'Normal reading'),
(2, '2026-07', 50.00, 58.00, '2026-07-28', 2, 'Normal reading'),
(2, '2026-08', 58.00, 70.00, '2026-08-28', 2, 'Normal reading'),
(3, '2026-08', 200.00, 235.00, '2026-08-28', 2, 'High consumption - check for leaks'),
(4, '2026-08', 30.00, 33.00, '2026-08-28', 2, 'Normal reading');

-- Sample water bills (amounts computed the same way computeBillAmount() does:
-- PHP 150 minimum charge (first 10 cu.m.) + PHP 15 per cu.m. beyond 10, senior discount 20%;
-- due on the 19th of the month after the billing month, disconnection 5 days later)
-- consumer 1, July: consumption 12 -> 150 + 2*15 = 180.00
INSERT INTO `water_bills` (`bill_number`, `consumer_id`, `reading_id`, `billing_period`, `consumption`, `rate_id`, `amount_due`, `discount_amount`, `penalty_amount`, `total_amount`, `amount_paid`, `bill_date`, `due_date`, `disconnection_date`, `status`, `generated_by`) VALUES
('BILL-2026-07-0001', 1, 1, '2026-07', 12.00, NULL, 180.00, 0.00, 0.00, 180.00, 180.00, '2026-07-29', '2026-08-19', '2026-08-24', 'paid', 2),
('BILL-2026-08-0001', 1, 2, '2026-08', 13.00, NULL, 195.00, 0.00, 0.00, 195.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid', 2),
('BILL-2026-07-0002', 2, 3, '2026-07', 8.00, NULL, 150.00, 0.00, 0.00, 150.00, 150.00, '2026-07-29', '2026-08-19', '2026-08-24', 'paid', 2),
('BILL-2026-08-0002', 2, 4, '2026-08', 12.00, NULL, 180.00, 0.00, 0.00, 180.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid', 2),
('BILL-2026-08-0003', 3, 5, '2026-08', 35.00, NULL, 525.00, 0.00, 0.00, 525.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid', 2),
('BILL-2026-08-0004', 4, 6, '2026-08', 3.00, NULL, 150.00, 0.00, 0.00, 150.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid', 2);

-- Sample payments (matching the "paid" bills above)
INSERT INTO `payments` (`payment_reference`, `bill_id`, `consumer_id`, `amount_paid`, `payment_method`, `payment_gateway_txn_id`, `payment_date`, `status`, `received_by`, `remarks`) VALUES
('PMT-2026-000001', 1, 1, 180.00, 'cash', NULL, '2026-08-01 10:15:00', 'verified', 2, 'Paid over the counter'),
('PMT-2026-000002', 3, 2, 150.00, 'gcash', 'GC-TXN-88213', '2026-08-02 14:30:00', 'verified', 2, 'Paid via GCash');

-- Sample notifications
INSERT INTO `notifications` (`user_id`, `bill_id`, `title`, `message`, `type`, `is_read`) VALUES
(3, 2, 'New Water Bill Available', 'Your water bill for August 2026 (PHP 195.00) is now available. Due on 2026-09-19.', 'bill_due', 0),
(4, 4, 'New Water Bill Available', 'Your water bill for August 2026 (PHP 180.00) is now available. Due on 2026-09-19.', 'bill_due', 0),
(3, 1, 'Payment Received', 'We received your payment of PHP 180.00 for bill BILL-2026-07-0001. Thank you!', 'payment_received', 1);

-- ---------------------------------------------------------------------
-- AGAS Assistant chatbot knowledge base (seed FAQs)
-- ---------------------------------------------------------------------
INSERT INTO `chatbot_faqs` (`question`, `answer`, `category`, `keywords`, `status`, `created_by`) VALUES
('What is my current water bill?', 'You can view your current bill anytime on the "Current Bill" page in your resident dashboard. It shows your consumption, amount due, any penalties, and your remaining balance.', 'billing', 'current bill, my bill, view bill, water bill', 'active', 1),
('How is my water bill calculated?', 'Your bill = Base Charge + (billable consumption x rate per cubic meter), based on the tiered rate bracket your consumption falls into. Rates are set by the barangay — see your printed bill for the exact breakdown.', 'billing', 'calculate, computation, formula, tier, rate, bracket', 'active', 1),
('What is my water consumption?', 'Your consumption is the difference between your current and previous meter readings for the billing period, measured in cubic meters (m3). Check it anytime on the "My Consumption" page.', 'billing', 'consumption, usage, cubic meter, water used', 'active', 1),
('When is my bill due?', 'Your exact due date is shown on your bill and on the "Current Bill" page — it is normally set a number of days after the bill date.', 'billing', 'due date, deadline, when pay', 'active', 1),
('What happens if my bill is overdue?', 'If a bill is not paid within the grace period after its due date, it is marked overdue and a penalty percentage (set by the barangay) is added to your total amount due.', 'billing', 'overdue, penalty, late payment, past due', 'active', 1),
('Where can I view my billing history?', 'Open "Billing History" in your resident menu to see all your past water bills and their statuses.', 'billing', 'billing history, past bills, previous bills', 'active', 1),
('Why is my water bill different this month?', 'Bill amounts change with your actual consumption each period, and can also rise if an overdue penalty was added or if billing rates were updated. Compare your readings on the "My Consumption" page.', 'billing', 'bill different, bill changed, why higher, why increased', 'active', 1),
('What is a meter reading?', 'A meter reading is the number recorded from your water meter dial by barangay staff each billing period. The difference between two consecutive readings equals your consumption for that period.', 'meter_reading', 'meter reading, what is reading', 'active', 1),
('How is my water consumption calculated from meter readings?', 'Consumption = Current Meter Reading - Previous Meter Reading, in cubic meters. This is recorded by our meter reader staff and used to compute your bill.', 'meter_reading', 'consumption calculation, current reading, previous reading', 'active', 1),
('How often is the meter read?', 'Meter readings are taken once every billing period, typically monthly, by barangay water staff.', 'meter_reading', 'how often, reading schedule, monthly reading', 'active', 1),
('What should I do if I think my meter reading is incorrect?', 'Please contact the Barangay Adlay water office with your account number and the reading in question. Meter readings can only be corrected by authorized staff, not through this chatbot.', 'meter_reading', 'wrong reading, incorrect reading, dispute reading', 'active', 1),
('What should I do if I suspect a water leak?', 'Please report it immediately to the Barangay Adlay water office so staff can inspect your meter and connection. An unusually high consumption on your "My Consumption" page can be a sign of a leak.', 'meter_reading', 'leak, water leak, high consumption, pipe leak', 'active', 1),
('How can I pay my water bill?', 'You can pay in person (cash) at the barangay water office, or submit an online payment (GCash, bank transfer, or online gateway) from the "Current Bill" page by entering the amount and your transaction reference number.', 'payments', 'how to pay, payment methods, gcash, bank transfer, online payment', 'active', 1),
('How can I check if my payment was recorded?', 'Open "Payment History" in your resident menu — every payment you have made is listed there along with its status: pending, verified, failed, or refunded.', 'payments', 'check payment, payment recorded, confirm payment', 'active', 1),
('Where can I see my payment history?', 'Go to "Payment History" in your resident dashboard menu to see all your submitted and verified payments.', 'payments', 'payment history, past payments', 'active', 1),
('Why is my payment still pending?', 'Online and self-service payments are marked "pending" until barangay staff verify them against the funds actually received. This manual check is usually completed within a few business days.', 'payments', 'payment pending, still pending, not verified', 'active', 1),
('What should I do if my payment was not reflected?', 'If it has been more than a few business days and your payment is still not verified or applied to your bill, please contact the Barangay Adlay water office with your payment reference number.', 'payments', 'payment not reflected, missing payment, payment not applied', 'active', 1),
('How do I register?', 'Go to the "Create Account" page and enter your meter number, full name, and purok exactly as registered with the barangay. If they match an existing consumer record without a linked login, your account will be created.', 'account', 'register, sign up, create account', 'active', 1),
('How do I log in?', 'Use the username and password you created during registration on the AGAS Login page.', 'account', 'log in, login, sign in', 'active', 1),
('How can I update my account information?', 'Go to "My Profile" in your resident menu to update your email and contact number, or to change your password.', 'account', 'update profile, update information, edit account', 'active', 1),
('I forgot my password. What should I do?', 'For security reasons, this chatbot cannot reset passwords. Please contact the Barangay Adlay water office or system administrator, and they can issue you a new temporary password.', 'account', 'forgot password, reset password, lost password', 'active', 1),
('How can I view my consumer information?', 'Your account number, address, purok, meter serial number, and connection status are all shown on the "My Profile" page.', 'account', 'consumer information, my account, account details', 'active', 1),
('What is AGAS?', 'AGAS (Smart Water Management and Billing System with Online Payment) is Barangay Adlay''s digital platform for meter reading, water billing, and payment monitoring.', 'system', 'what is agas, about agas', 'active', 1),
('What services does AGAS provide?', 'AGAS lets you view your water bills and consumption, review your billing and payment history, and submit online payments — all without visiting the barangay office in person.', 'system', 'services, features, what can agas do', 'active', 1),
('How can I use the AGAS dashboard?', 'Your dashboard shows your latest consumption, current bill balance, due date, payment status, a consumption trend chart, and your recent payments — everything at a glance.', 'system', 'use dashboard, dashboard help', 'active', 1),
('Where can I see my current bill?', 'Click "Current Bill" in your resident menu to see your active bill(s) and to submit a payment.', 'system', 'current bill page, see my bill', 'active', 1),
('Where can I see my previous bills?', 'Click "Billing History" in your resident menu to see all bills from previous billing periods.', 'system', 'previous bills, past bills, billing history page', 'active', 1);

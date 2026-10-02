-- =====================================================================
-- Migration: AGAS Assistant chatbot (FAQ knowledge base)
-- ---------------------------------------------------------------------
-- Run this once against an EXISTING awas_db database to add chatbot
-- support without re-running the full schema in awas_db.sql. Safe to
-- re-run: tables use IF NOT EXISTS and seed rows use INSERT IGNORE
-- against the unique `question` key.
-- =====================================================================

USE `awas_db`;

CREATE TABLE IF NOT EXISTS `chatbot_faqs` (
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

CREATE TABLE IF NOT EXISTS `chatbot_unanswered` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question` VARCHAR(500) NOT NULL,
  `asked_by` INT UNSIGNED DEFAULT NULL COMMENT 'resident user_id, nullable if the account is later removed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_unanswered_user` FOREIGN KEY (`asked_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('chatbot_ai_enabled', '0', 'Whether the AGAS Assistant chatbot may fall back to the AI API for questions the FAQ knowledge base cannot answer (requires an API key configured in config.php)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT IGNORE INTO `chatbot_faqs` (`question`, `answer`, `category`, `keywords`, `status`, `created_by`) VALUES
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

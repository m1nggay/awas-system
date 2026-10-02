-- =====================================================================
-- Migration: fixed billing formula, senior discount, disconnection date
-- ---------------------------------------------------------------------
--   Minimum charge PHP 150.00 (covers the first 10 cu. m.)
--   Excess cu. m. billed at PHP 15.00 each
--   Senior citizens get 20% off the sub-total
--   Due date = 19th of the month after the billing month
--   Disconnection date = 5 days after the due date
-- Run this once against an EXISTING awas_db database. Safe to re-run.
-- Bills already generated keep their old amounts.
-- =====================================================================

USE `awas_db`;

ALTER TABLE `consumers`
  ADD COLUMN IF NOT EXISTS `is_senior` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`;

ALTER TABLE `water_bills`
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `amount_due`,
  ADD COLUMN IF NOT EXISTS `disconnection_date` DATE DEFAULT NULL AFTER `due_date`;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('minimum_charge', '150', 'Minimum water charge in PHP, always billed'),
('minimum_cubic_meters', '10', 'Cubic meters covered by the minimum charge'),
('excess_rate_per_cubic_meter', '15', 'PHP per cubic meter beyond the minimum'),
('senior_discount_percent', '20', 'Discount percentage on the sub-total for senior citizens'),
('due_day_of_month', '19', 'Day of the month after the billing month on which the bill is due'),
('disconnection_days', '5', 'Days after the due date on which service is subject to disconnection')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

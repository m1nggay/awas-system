-- =====================================================================
-- Migration: PayMongo online payments
-- ---------------------------------------------------------------------
-- Run this once against an EXISTING awas_db database. Safe to re-run.
-- =====================================================================

USE `awas_db`;

ALTER TABLE `payments`
  MODIFY COLUMN `payment_method` ENUM('cash','online','gcash','bank_transfer','paymongo','other') NOT NULL DEFAULT 'cash';

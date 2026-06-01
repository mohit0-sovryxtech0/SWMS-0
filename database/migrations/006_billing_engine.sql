-- Migration 006: Billing Engine enhancements
-- Adds default billing settings and support tables

-- Billing settings defaults
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group) VALUES
('billing_cycle_days', '30', 'billing'),
('due_date_days', '15', 'billing'),
('penalty_percent', '5.00', 'billing'),
('vat_percent', '0.00', 'billing'),
('meter_rent', '50.00', 'billing'),
('sewerage_fee', '0.00', 'billing'),
('min_units', '10', 'billing'),
('min_charge', '150', 'billing'),
('rate_per_unit', '10', 'billing'),
('default_currency', 'NRs.', 'billing');

-- Bill items table for detailed billing line items
CREATE TABLE IF NOT EXISTS `bill_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `bill_id` BIGINT UNSIGNED NOT NULL,
    `item_type` VARCHAR(50) NOT NULL COMMENT 'base_fee, consumption, meter_rent, sewerage, vat, penalty, discount',
    `description` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add vat_percent column to bills if not exists
ALTER TABLE `bills` ADD COLUMN IF NOT EXISTS `vat_percent` DECIMAL(5,2) DEFAULT 0 AFTER `vat_amount`;

-- Add billing_cycle columns to consumers for tracking
ALTER TABLE `consumers` ADD COLUMN IF NOT EXISTS `last_billed_date` DATE DEFAULT NULL AFTER `remarks`;
ALTER TABLE `consumers` ADD COLUMN IF NOT EXISTS `next_billing_date` DATE DEFAULT NULL AFTER `last_billed_date`;

-- Add index for billing queries
ALTER TABLE `bills` ADD INDEX IF NOT EXISTS `idx_bills_status_due` (`status`, `due_date`);
ALTER TABLE `bills` ADD INDEX IF NOT EXISTS `idx_bills_consumer_status` (`consumer_id`, `status`);
ALTER TABLE `payments` ADD INDEX IF NOT EXISTS `idx_payments_consumer_date` (`consumer_id`, `payment_date`);
ALTER TABLE `payments` ADD INDEX IF NOT EXISTS `idx_payments_status_method` (`status`, `payment_method`);

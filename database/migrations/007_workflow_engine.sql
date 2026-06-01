-- ============================================================
-- Migration 007: Workflow Engine
-- Adds: Meter reading routes, schedules, batch ops,
--        billing cycles, enhanced workflow tables
-- ============================================================

-- 1. METER READING ROUTES
CREATE TABLE IF NOT EXISTS meter_reading_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(50) NOT NULL UNIQUE,
    route_name VARCHAR(200) NOT NULL,
    ward_no INT,
    area_description TEXT,
    estimated_consumers INT DEFAULT 0,
    assigned_reader_id BIGINT UNSIGNED,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (assigned_reader_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_route_ward (ward_no),
    INDEX idx_route_reader (assigned_reader_id)
) ENGINE=InnoDB;

-- Route-consumer assignment
CREATE TABLE IF NOT EXISTS route_consumers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_id BIGINT UNSIGNED NOT NULL,
    consumer_id BIGINT UNSIGNED NOT NULL,
    sequence_no INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES meter_reading_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_route_consumer (route_id, consumer_id),
    INDEX idx_route_seq (route_id, sequence_no)
) ENGINE=InnoDB;

-- 2. METER READING SCHEDULES
CREATE TABLE IF NOT EXISTS meter_reading_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_id BIGINT UNSIGNED NOT NULL,
    fiscal_year_id BIGINT UNSIGNED NOT NULL,
    billing_month TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    schedule_start DATE NOT NULL,
    schedule_end DATE NOT NULL,
    target_consumers INT DEFAULT 0,
    readings_taken INT DEFAULT 0,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to BIGINT UNSIGNED,
    notes TEXT,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES meter_reading_routes(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_sched_route (route_id),
    INDEX idx_sched_month (billing_month),
    INDEX idx_sched_status (status)
) ENGINE=InnoDB;

-- 3. BILLING CYCLES
CREATE TABLE IF NOT EXISTS billing_cycles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_code VARCHAR(50) NOT NULL UNIQUE,
    fiscal_year_id BIGINT UNSIGNED NOT NULL,
    billing_month TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    due_date DATE NOT NULL,
    reading_cutoff_date DATE,
    target_consumers INT DEFAULT 0,
    bills_generated INT DEFAULT 0,
    total_billed DECIMAL(14,2) DEFAULT 0,
    total_collected DECIMAL(14,2) DEFAULT 0,
    status ENUM('draft', 'reading_in_progress', 'billing_in_progress', 'bills_generated', 'collection_in_progress', 'closed', 'cancelled') DEFAULT 'draft',
    generated_by BIGINT UNSIGNED,
    generated_at TIMESTAMP NULL,
    closed_by BIGINT UNSIGNED,
    closed_at TIMESTAMP NULL,
    notes TEXT,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    FOREIGN KEY (generated_by) REFERENCES users(id),
    FOREIGN KEY (closed_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_cycle_month (billing_month),
    INDEX idx_cycle_status (status),
    INDEX idx_cycle_fy (fiscal_year_id)
) ENGINE=InnoDB;

-- Link cycles to bills
ALTER TABLE bills ADD COLUMN IF NOT EXISTS billing_cycle_id BIGINT UNSIGNED AFTER fiscal_year_id;
ALTER TABLE bills ADD COLUMN IF NOT EXISTS last_reminder_sent_at TIMESTAMP NULL AFTER remarks;
ALTER TABLE bills ADD COLUMN IF NOT EXISTS last_reminder_type VARCHAR(50) AFTER last_reminder_sent_at;
ALTER TABLE bills ADD COLUMN IF NOT EXISTS discount_reason VARCHAR(255) AFTER discount_amount;
ALTER TABLE bills ADD COLUMN IF NOT EXISTS printed_at TIMESTAMP NULL AFTER paid_at;

-- 4. ENHANCE METER READINGS
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS route_id BIGINT UNSIGNED AFTER meter_id;
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS reading_batch_id BIGINT UNSIGNED AFTER route_id;
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'synced' AFTER reading_source;
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS sync_device_id VARCHAR(255) AFTER sync_status;
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS weather_condition VARCHAR(100) AFTER gps_longitude;
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS meter_condition ENUM('good', 'damaged', 'tampered', 'not_accessible') DEFAULT 'good' AFTER meter_photo;

-- 5. READING BATCHES (for offline sync)
CREATE TABLE IF NOT EXISTS meter_reading_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_code VARCHAR(50) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    route_id BIGINT UNSIGNED,
    total_readings INT DEFAULT 0,
    synced_readings INT DEFAULT 0,
    status ENUM('pending', 'syncing', 'completed', 'partial') DEFAULT 'pending',
    device_id VARCHAR(255),
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (route_id) REFERENCES meter_reading_routes(id),
    INDEX idx_batch_user (user_id),
    INDEX idx_batch_status (status)
) ENGINE=InnoDB;

-- 6. BILL NOTIFICATIONS LOG
CREATE TABLE IF NOT EXISTS bill_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id BIGINT UNSIGNED NOT NULL,
    consumer_id BIGINT UNSIGNED NOT NULL,
    notification_type ENUM('bill_generated', 'due_reminder', 'overdue_reminder', 'payment_confirmation', 'disconnection_warning') NOT NULL,
    channel ENUM('sms', 'email', 'whatsapp', 'in_app') DEFAULT 'sms',
    recipient VARCHAR(255) NOT NULL,
    message TEXT,
    status ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
    provider_ref VARCHAR(255),
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id),
    INDEX idx_notif_bill (bill_id),
    INDEX idx_notif_type (notification_type),
    INDEX idx_notif_status (status)
) ENGINE=InnoDB;

-- 7. PAYMENT RECONCILIATION LOG
CREATE TABLE IF NOT EXISTS payment_reconciliation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    reconciled_date DATE NOT NULL,
    expected_amount DECIMAL(12,2) NOT NULL,
    actual_amount DECIMAL(12,2) NOT NULL,
    difference DECIMAL(12,2) DEFAULT 0,
    reconciled_by BIGINT UNSIGNED NOT NULL,
    status ENUM('matched', 'mismatch', 'unreconciled') DEFAULT 'matched',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (reconciled_by) REFERENCES users(id),
    INDEX idx_recon_date (reconciled_date),
    INDEX idx_recon_status (status)
) ENGINE=InnoDB;

-- 8. WORKFLOW AUDIT TRAIL
CREATE TABLE IF NOT EXISTS workflow_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_type ENUM('meter_reading', 'billing', 'payment', 'collection', 'report') NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id BIGINT UNSIGNED,
    old_value TEXT,
    new_value TEXT,
    performed_by BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_audit_workflow (workflow_type),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user (performed_by),
    INDEX idx_audit_time (created_at)
) ENGINE=InnoDB;

-- 9. INDEXES FOR PERFORMANCE
-- Note: MySQL 8.0 does NOT support IF NOT EXISTS for CREATE INDEX
-- Skipping idx_bill_cycle on billing_cycle_id since that column is new and may be NULL for existing rows
-- Index creation is handled in the migration runner script

-- 10. DEFAULT ROUTE DATA (ward-based)
INSERT INTO meter_reading_routes (route_code, route_name, ward_no, area_description) VALUES
('RTE-W001', 'Ward 1 Route', 1, 'All consumers in Ward 1'),
('RTE-W002', 'Ward 2 Route', 2, 'All consumers in Ward 2'),
('RTE-W003', 'Ward 3 Route', 3, 'All consumers in Ward 3'),
('RTE-W004', 'Ward 4 Route', 4, 'All consumers in Ward 4'),
('RTE-W005', 'Ward 5 Route', 5, 'All consumers in Ward 5'),
('RTE-W006', 'Ward 6 Route', 6, 'All consumers in Ward 6'),
('RTE-W007', 'Ward 7 Route', 7, 'All consumers in Ward 7'),
('RTE-W008', 'Ward 8 Route', 8, 'All consumers in Ward 8'),
('RTE-W009', 'Ward 9 Route', 9, 'All consumers in Ward 9'),
('RTE-W010', 'Ward 10 Route', 10, 'All consumers in Ward 10')
ON DUPLICATE KEY UPDATE route_name = VALUES(route_name);

-- ============================================================
-- SWMS Migration 008: Reading Verification & Workflow Enhancement
-- Version: 1.0.0
-- Description: Adds reading_verifications table, bill published_at,
--              enhances meter_readings for complete workflow
-- ============================================================

-- 1. Reading Verifications (audit trail for approve/reject)
CREATE TABLE IF NOT EXISTS reading_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reading_id BIGINT UNSIGNED NOT NULL,
    verified_by BIGINT UNSIGNED NOT NULL,
    action ENUM('approved', 'rejected') NOT NULL,
    previous_status VARCHAR(50) DEFAULT 'pending',
    new_status VARCHAR(50) DEFAULT 'verified',
    remarks TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reading_id) REFERENCES meter_readings(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_rv_reading (reading_id),
    INDEX idx_rv_verifier (verified_by),
    INDEX idx_rv_action (action),
    INDEX idx_rv_created (created_at)
) ENGINE=InnoDB;

-- 2. Add reading_status to meter_readings for full workflow tracking
ALTER TABLE meter_readings
    ADD COLUMN IF NOT EXISTS reading_status ENUM('draft','submitted','pending_verification','verified','rejected','resubmitted') DEFAULT 'submitted' AFTER is_estimated,
    ADD COLUMN IF NOT EXISTS rejected_by BIGINT UNSIGNED AFTER verified_by,
    ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL AFTER verified_at,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT AFTER remarks,
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP NULL AFTER reading_date,
    ADD COLUMN IF NOT EXISTS resubmitted_at TIMESTAMP NULL AFTER submitted_at,
    ADD INDEX IF NOT EXISTS idx_reading_status (reading_status);

-- 3. Reading Documents/Photos (supports multiple photos per reading)
CREATE TABLE IF NOT EXISTS reading_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reading_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('meter_photo', 'installation_photo', 'gps_proof', 'consumer_photo', 'other') DEFAULT 'meter_photo',
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255),
    file_size BIGINT,
    mime_type VARCHAR(100),
    gps_latitude DECIMAL(10,7),
    gps_longitude DECIMAL(10,7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reading_id) REFERENCES meter_readings(id) ON DELETE CASCADE,
    INDEX idx_rd_reading (reading_id)
) ENGINE=InnoDB;

-- 4. Add published_at to bills for citizen portal visibility tracking
ALTER TABLE bills
    ADD COLUMN IF NOT EXISTS published_at TIMESTAMP NULL AFTER generated_at,
    ADD COLUMN IF NOT EXISTS published_by BIGINT UNSIGNED AFTER published_at,
    ADD COLUMN IF NOT EXISTS reading_id BIGINT UNSIGNED AFTER meter_id,
    ADD INDEX IF NOT EXISTS idx_bill_reading (reading_id),
    ADD INDEX IF NOT EXISTS idx_bill_published (published_at);

-- 5. Bill Notifications (already created in 007, add if missing)
CREATE TABLE IF NOT EXISTS bill_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id BIGINT UNSIGNED NOT NULL,
    consumer_id BIGINT UNSIGNED NOT NULL,
    notification_type ENUM('sms', 'email', 'both') DEFAULT 'sms',
    channel ENUM('sms', 'email', 'push', 'system') DEFAULT 'sms',
    message TEXT,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    gateway_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id),
    INDEX idx_bn_bill (bill_id),
    INDEX idx_bn_consumer (consumer_id),
    INDEX idx_bn_status (status)
) ENGINE=InnoDB;

-- 6. Payment Reconciliation Log
CREATE TABLE IF NOT EXISTS payment_reconciliation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    gateway_type ENUM('esewa', 'khalti', 'fonepay', 'qr', 'bank', 'cash') DEFAULT 'cash',
    transaction_id VARCHAR(200),
    gateway_transaction_id VARCHAR(200),
    amount DECIMAL(12,2) NOT NULL,
    gateway_fee DECIMAL(12,2) DEFAULT 0,
    net_amount DECIMAL(12,2) NOT NULL,
    reconciliation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('matched', 'mismatched', 'pending', 'settled') DEFAULT 'matched',
    notes TEXT,
    reconciled_by BIGINT UNSIGNED,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (reconciled_by) REFERENCES users(id),
    INDEX idx_pr_payment (payment_id),
    INDEX idx_pr_transaction (transaction_id),
    INDEX idx_pr_gateway (gateway_type),
    INDEX idx_pr_status (status)
) ENGINE=InnoDB;

-- 7. Add new permissions for enhanced workflow
INSERT IGNORE INTO permissions (name, slug, module, description) VALUES
('Publish Bills', 'bills.publish', 'Billing', 'Publish bills to citizen portal'),
('Reconcile Payments', 'payments.reconcile', 'Billing', 'Reconcile payment gateway transactions'),
('View Reading Verifications', 'verifications.view', 'Meter', 'View reading verification history'),
('Reject Readings', 'readings.reject', 'Meter', 'Reject meter readings'),
('Export Citizen Data', 'citizen.export', 'Citizen', 'Export citizen portal data'),
('Manage Notifications', 'notifications.manage', 'Notifications', 'Manage bill notifications');

-- Assign new permissions to Super Admin and Billing Officer roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug IN ('super_admin', 'committee_admin', 'manager', 'billing_officer')
AND p.slug IN ('bills.publish', 'payments.reconcile', 'verifications.view', 'readings.reject', 'notifications.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug IN ('super_admin', 'committee_admin')
AND p.slug IN ('citizen.export');

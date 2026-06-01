-- Migration: Create notification_templates table
-- Run: mysql -u root swms_db < database/migrations/002_notification_templates.sql

CREATE TABLE IF NOT EXISTS notification_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    type ENUM('sms', 'email', 'both', 'system') DEFAULT 'email',
    subject VARCHAR(300),
    body TEXT NOT NULL,
    variables TEXT COMMENT 'JSON array of placeholder variables',
    is_active TINYINT(1) DEFAULT 1,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_template_slug (slug),
    INDEX idx_template_active (is_active)
) ENGINE=InnoDB;

-- Seed default templates
INSERT INTO notification_templates (name, slug, type, subject, body, variables, is_active) VALUES
('Bill Reminder', 'bill_reminder', 'both', 'Bill Reminder - {consumer_name}', 'Dear {consumer_name},\n\nYour water bill of {amount} is due on {due_date}. Bill No: {bill_no}.\n\nPlease pay before the due date to avoid penalty.\n\nThank you,\n{app_name}', '["consumer_name","bill_no","amount","due_date","app_name"]', 1),
('Payment Confirmation', 'payment_confirmation', 'both', 'Payment Confirmed - {consumer_name}', 'Dear {consumer_name},\n\nYour payment of {amount} for Bill No: {bill_no} has been received successfully.\nReceipt No: {receipt_no}\nDate: {payment_date}\n\nThank you,\n{app_name}', '["consumer_name","bill_no","amount","receipt_no","payment_date","app_name"]', 1),
('Complaint Received', 'complaint_received', 'both', 'Complaint Registered - {ticket_no}', 'Dear {consumer_name},\n\nYour complaint has been registered successfully.\nTicket No: {ticket_no}\nSubject: {subject}\n\nWe will address it at the earliest.\n\nThank you,\n{app_name}', '["consumer_name","ticket_no","subject","app_name"]', 1),
('Complaint Resolved', 'complaint_resolved', 'both', 'Complaint Resolved - {ticket_no}', 'Dear {consumer_name},\n\nYour complaint (Ticket No: {ticket_no}) has been resolved.\nSubject: {subject}\nResolution: {resolution}\n\nPlease provide your feedback.\n\nThank you,\n{app_name}', '["consumer_name","ticket_no","subject","resolution","app_name"]', 1),
('Service Disconnection Notice', 'service_disconnection', 'both', 'Service Disconnection Notice - {consumer_no}', 'Dear {consumer_name},\n\nYour water connection ({consumer_no}) is scheduled for disconnection on {disconnection_date} due to non-payment of {amount}.\n\nPlease clear the dues to avoid disconnection.\n\nThank you,\n{app_name}', '["consumer_name","consumer_no","disconnection_date","amount","app_name"]', 1),
('Due Reminder', 'due_reminder', 'both', 'Payment Due Reminder - {consumer_name}', 'Dear {consumer_name},\n\nThis is a reminder that your water bill of {amount} for Bill No: {bill_no} is overdue by {overdue_days} days.\n\nPlease pay immediately to avoid penalty and disconnection.\n\nThank you,\n{app_name}', '["consumer_name","bill_no","amount","overdue_days","app_name"]', 1);

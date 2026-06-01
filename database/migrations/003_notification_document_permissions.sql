-- Migration: Add notifications and documents permissions
-- Run: mysql -u root swms_db < database/migrations/003_notification_document_permissions.sql

INSERT IGNORE INTO permissions (name, slug, module, description) VALUES
('View Notifications', 'notifications.view', 'Notifications', 'View and send notifications'),
('Manage Notification Templates', 'templates.manage', 'Notifications', 'Create/edit notification templates'),
('Configure Notification Settings', 'settings.notifications', 'Notifications', 'Configure SMTP and SMS settings'),
('View Documents', 'documents.view', 'Documents', 'View document repository'),
('Upload Documents', 'documents.upload', 'Documents', 'Upload documents'),
('Delete Documents', 'documents.delete', 'Documents', 'Delete documents');

-- Assign new permissions to Super Admin (role_id = 1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE slug IN ('notifications.view', 'templates.manage', 'settings.notifications', 'documents.view', 'documents.upload', 'documents.delete');

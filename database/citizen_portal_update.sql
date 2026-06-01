-- Citizen Portal Schema Update for SWMS
-- Adds password authentication fields to consumers table

ALTER TABLE consumers ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER email;
ALTER TABLE consumers ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL AFTER password;
ALTER TABLE consumers ADD COLUMN reset_expires TIMESTAMP NULL AFTER reset_token;
ALTER TABLE consumers ADD COLUMN registered_at TIMESTAMP NULL DEFAULT NULL AFTER reset_expires;

-- Migration: Add consumption_flag to meter_readings
-- Run: mysql -u root swms_db < migrations/001_add_consumption_flag.sql

ALTER TABLE meter_readings
ADD COLUMN consumption_flag VARCHAR(20) DEFAULT NULL AFTER consumption,
ADD INDEX idx_reading_verified (is_verified),
ADD INDEX idx_reading_reader (read_by);

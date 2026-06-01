-- Migration: Fix missing columns in existing database
-- This adds columns that are referenced by code but missing from actual tables

-- Add password column to consumers (for citizen portal authentication)
ALTER TABLE consumers ADD COLUMN IF NOT EXISTS password VARCHAR(255) AFTER photo;
ALTER TABLE consumers ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) AFTER password;
ALTER TABLE consumers ADD COLUMN IF NOT EXISTS reset_expires DATETIME AFTER reset_token;
ALTER TABLE consumers ADD COLUMN IF NOT EXISTS registered_at TIMESTAMP NULL AFTER reset_expires;
ALTER TABLE consumers ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED AFTER created_by;

-- Add bill_date and deleted_at to bills (referenced by dashboard, consumers/view, defaulters)
ALTER TABLE bills ADD COLUMN IF NOT EXISTS bill_date DATE AFTER fiscal_year_id;
ALTER TABLE bills ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL AFTER updated_at;

-- Add title and original_name columns to consumer_documents (used in upload form)
ALTER TABLE consumer_documents ADD COLUMN IF NOT EXISTS title VARCHAR(200) AFTER document_type;
ALTER TABLE consumer_documents ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) AFTER title;

-- Add consumption_flag to meter_readings (referenced by migration 001)
ALTER TABLE meter_readings ADD COLUMN IF NOT EXISTS consumption_flag ENUM('normal', 'high', 'low', 'zero') DEFAULT 'normal' AFTER consumption;

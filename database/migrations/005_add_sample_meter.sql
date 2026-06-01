-- Migration: Add a sample meter for POS meter reading demo
-- This ensures the meter-reading page has data to work with.

-- Add a sample meter assigned to the first consumer
INSERT INTO meters (meter_no, consumer_id, meter_type, initial_reading, last_reading, status, created_at)
SELECT 'MTR-0001', id, 'domestic', 0, 0, 'active', NOW()
FROM consumers
WHERE deleted_at IS NULL
AND id = (SELECT MIN(id) FROM consumers WHERE deleted_at IS NULL)
AND NOT EXISTS (SELECT 1 FROM meters WHERE meter_no = 'MTR-0001')
LIMIT 1;

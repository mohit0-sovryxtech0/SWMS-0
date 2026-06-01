<?php
require_once __DIR__ . '/../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('readings.view');

header('Content-Type: application/json');

$search = trim(get('q', ''));
if (strlen($search) < 2) {
    json_error('Enter at least 2 characters');
}

$consumer = db()->fetchOne(
    "SELECT c.id, c.consumer_no, c.full_name, c.phone, c.mobile, c.ward_no, c.tole,
            c.connection_type, c.status,
            m.id as meter_id, m.meter_no, m.initial_reading, m.last_reading,
            m.last_reading_date, m.gps_latitude as meter_lat,
            m.gps_longitude as meter_lng, m.meter_type, m.meter_brand,
            m.meter_model, m.meter_size, m.installation_date, m.status as meter_status,
            (SELECT current_reading FROM meter_readings 
             WHERE meter_id = m.id AND is_verified = 1 
             ORDER BY reading_date DESC LIMIT 1) as actual_last_reading
     FROM consumers c
     LEFT JOIN meters m ON m.consumer_id = c.id AND m.deleted_at IS NULL
     WHERE (c.consumer_no LIKE :q1 OR c.full_name LIKE :q2 OR c.mobile LIKE :q3 OR m.meter_no LIKE :q4)
     AND c.deleted_at IS NULL
     LIMIT 1",
    [
        'q1' => "%{$search}%",
        'q2' => "%{$search}%",
        'q3' => "%{$search}%",
        'q4' => "%{$search}%"
    ]
);

if (!$consumer) {
    json_error('No consumer found matching your search');
}

$readings = db()->fetchAll(
    "SELECT id, reading_date, current_reading, consumption, is_verified, remarks, created_at
     FROM meter_readings WHERE meter_id = :meter_id AND is_verified = 1
     ORDER BY reading_date DESC LIMIT 5",
    ['meter_id' => $consumer['meter_id']]
);

$consumer['readings'] = $readings;

$avgConsumption = db()->fetchColumn(
    "SELECT COALESCE(AVG(consumption), 0) FROM meter_readings 
     WHERE meter_id = :meter_id AND is_verified = 1 AND consumption > 0",
    ['meter_id' => $consumer['meter_id']]
);
$consumer['avg_consumption'] = round((float)$avgConsumption, 2);

json_success($consumer, 'Consumer found');

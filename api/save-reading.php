<?php
require_once __DIR__ . '/../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('readings.enter');

header('Content-Type: application/json');

if (!isPost()) {
    json_error('Invalid request method');
}

$token = post('csrf_token');
if (!verify_csrf($token)) {
    json_error('Invalid security token. Please refresh the page.');
}

$validator = validator($_POST, [
    'consumer_id' => 'required|numeric',
    'meter_id' => 'required|numeric',
    'current_reading' => 'required|numeric',
    'reading_date' => 'required|date',
]);

$validator->setFieldNames([
    'consumer_id' => 'Consumer',
    'meter_id' => 'Meter',
    'current_reading' => 'Current reading',
    'reading_date' => 'Reading date',
]);

if ($validator->fails()) {
    json_error('Validation failed', $validator->allErrors());
}

$consumerId = (int)post('consumer_id');
$meterId = (int)post('meter_id');
$currentReading = (float)post('current_reading');
$readingDate = post('reading_date');
$remarks = post('remarks', '');
$latitude = post('latitude');
$longitude = post('longitude');
$isEstimated = (int)post('is_estimated', 0);

$consumer = db()->fetchOne(
    "SELECT id, consumer_no, full_name FROM consumers WHERE id = :id AND deleted_at IS NULL",
    ['id' => $consumerId]
);
if (!$consumer) {
    json_error('Consumer not found');
}

$meter = db()->fetchOne(
    "SELECT id, meter_no, last_reading, last_reading_date, initial_reading, status 
     FROM meters WHERE id = :id AND deleted_at IS NULL",
    ['id' => $meterId]
);
if (!$meter) {
    json_error('Meter not found');
}

if ($meter['status'] !== 'active') {
    json_error('Meter is not active (status: ' . $meter['status'] . ')');
}

$previousReading = (float)$meter['last_reading'];
if ($previousReading <= 0 && (float)$meter['initial_reading'] > 0) {
    $previousReading = (float)$meter['initial_reading'];
}

if ($currentReading < $previousReading) {
    json_error('Current reading (' . $currentReading . ') cannot be less than previous reading (' . $previousReading . '). Please check the meter value.');
}

$consumption = $currentReading - $previousReading;

$avgConsumption = db()->fetchColumn(
    "SELECT COALESCE(AVG(consumption), 0) FROM meter_readings 
     WHERE meter_id = :meter_id AND is_verified = 1 AND consumption > 0",
    ['meter_id' => $meterId]
);

$consumptionFlag = null;
if ($avgConsumption > 0) {
    $ratio = $consumption / $avgConsumption;
    if ($ratio > 2) {
        $consumptionFlag = 'high';
    } elseif ($consumption <= 0) {
        $consumptionFlag = 'zero';
    }
}

$photoPath = null;
if (!empty($_FILES['meter_photo']) && $_FILES['meter_photo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = UPLOADS_PATH . 'meter-readings/';
    $result = upload_file($_FILES['meter_photo'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    if ($result) {
        $photoPath = 'meter-readings/' . $result;
    }
}

try {
    db()->beginTransaction();

    $readingId = db()->insert('meter_readings', [
        'consumer_id' => $consumerId,
        'meter_id' => $meterId,
        'reading_date' => $readingDate,
        'previous_reading' => $previousReading,
        'current_reading' => $currentReading,
        'consumption' => $consumption,
        'reading_source' => 'manual',
        'meter_photo' => $photoPath,
        'gps_latitude' => $latitude ?: null,
        'gps_longitude' => $longitude ?: null,
        'is_estimated' => $isEstimated,
        'is_verified' => 0,
        'remarks' => $remarks,
        'consumption_flag' => $consumptionFlag,
        'read_by' => Auth::id(),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    db()->commit();

    log_activity(Auth::id(), 'Reading Entered', 'Meter Reading',
        "Reading {$currentReading} entered for meter {$meter['meter_no']} (consumer: {$consumer['consumer_no']})",
        ['reading_id' => $readingId, 'consumption' => $consumption]
    );

    json_success([
        'reading_id' => $readingId,
        'consumer_id' => $consumerId,
        'meter_no' => $meter['meter_no'],
        'previous_reading' => $previousReading,
        'current_reading' => $currentReading,
        'consumption' => $consumption,
        'consumption_flag' => $consumptionFlag,
        'avg_consumption' => round((float)$avgConsumption, 2),
    ], 'Reading saved successfully. Waiting for verification.');
} catch (Exception $e) {
    db()->rollback();
    error_log("Save reading error: " . $e->getMessage());
    json_error('Failed to save reading. Please try again.');
}

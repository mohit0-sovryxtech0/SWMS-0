<?php
require_once __DIR__ . '/../includes/config.php';
Auth::requireAuth();
RBAC::requirePermission('readings.verify');

try {
    $action = post('action');
    $readingId = intval(post('reading_id'));

    if (empty($action) || !$readingId) {
        json_error('Invalid request parameters');
    }

    if (!verify_csrf(post('csrf_token'))) {
        json_error('Security validation failed');
    }

    if (!in_array($action, ['approve', 'reject'])) {
        json_error('Invalid action');
    }

    $reading = db()->fetchOne(
        "SELECT mr.*, m.meter_no, m.id AS meter_id, c.consumer_no, c.full_name AS consumer_name,
                u.name AS reader_name
         FROM meter_readings mr
         JOIN meters m ON mr.meter_id = m.id
         JOIN consumers c ON mr.consumer_id = c.id
         LEFT JOIN users u ON mr.read_by = u.id
         WHERE mr.id = ?",
        [$readingId]
    );

    if (!$reading) {
        json_error('Reading not found');
    }

    if ($reading['is_verified'] && $action === 'approve') {
        json_error('Reading is already verified');
    }

    $verifierId = Auth::id();
    $remarks = post('remarks', '');

    db()->beginTransaction();
    try {
        if ($action === 'approve') {
            db()->update('meter_readings', [
                'is_verified' => 1,
                'verified_by' => $verifierId,
                'verified_at' => date('Y-m-d H:i:s'),
                'remarks' => $remarks ?: null
            ], 'id = :id', ['id' => $readingId]);

            db()->update('meters', [
                'last_reading' => $reading['current_reading'],
                'last_reading_date' => $reading['reading_date'] . ' ' . date('H:i:s'),
            ], 'id = :meter_id', ['meter_id' => $reading['meter_id']]);
        } else {
            db()->update('meter_readings', [
                'is_verified' => 0,
                'remarks' => $remarks
            ], 'id = :id', ['id' => $readingId]);
        }

        $actionLabel = $action === 'approve' ? 'approved' : 'rejected';
        log_activity($verifierId, "reading_{$actionLabel}", 'meter_reading',
            "Reading #{$readingId} for meter {$reading['meter_no']} ({$reading['consumer_name']}) was {$actionLabel}",
            ['reading_id' => $readingId, 'action' => $action]
        );

        db()->commit();

        json_success([
            'reading_id' => $readingId,
            'new_status' => $action === 'approve' ? 'verified' : 'rejected',
            'meter_no' => $reading['meter_no'],
            'consumer_name' => $reading['consumer_name']
        ], "Reading #{$readingId} has been {$actionLabel} successfully");
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }
} catch (Exception $e) {
    json_error($e->getMessage());
}

<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('consumers.delete');

if (!isPost()) {
    redirect(ADMIN_URL . 'consumers/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$consumerId = (int) post('consumer_id', 0);

if ($consumerId <= 0) {
    alert_error('Invalid consumer ID.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$consumer = db()->fetchOne("SELECT id, consumer_no, full_name FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);
if (!$consumer) {
    alert_error('Consumer not found.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

try {
    db()->beginTransaction();

    db()->softDelete('consumers', $consumerId);
    db()->update('consumers', [
        'status' => 'inactive',
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $consumerId]);

    db()->insert('consumer_history', [
        'consumer_id' => $consumerId,
        'action' => 'deleted',
        'old_value' => json_encode($consumer),
        'new_value' => null,
        'changed_by' => Auth::id(),
        'changed_at' => date('Y-m-d H:i:s'),
    ]);

    db()->commit();

    log_activity(Auth::id(), 'delete', 'consumers', "Deleted consumer: {$consumer['consumer_no']} - {$consumer['full_name']}", ['consumer_id' => $consumerId]);
    Security::logSecurityEvent('consumer_deleted', ['consumer_id' => $consumerId, 'consumer_no' => $consumer['consumer_no']]);

    alert_success('Consumer deleted successfully.');
} catch (Exception $e) {
    db()->rollback();
    error_log("Consumer delete error: " . $e->getMessage());
    alert_error('Failed to delete consumer. Please try again.');
}

if (isAjax()) {
    json_success([], 'Consumer deleted successfully.');
}

redirect(ADMIN_URL . 'consumers/index.php');

<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('complaints.create');

if (!isPost()) {
    redirect(ADMIN_URL . 'complaints/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaintId = (int) post('complaint_id', 0);
if ($complaintId <= 0) {
    alert_error('Invalid complaint ID.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaint = db()->fetchOne("SELECT id, ticket_no, subject FROM complaints WHERE id = ? AND deleted_at IS NULL", [$complaintId]);
if (!$complaint) {
    alert_error('Complaint not found.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

try {
    db()->beginTransaction();

    db()->softDelete('complaints', $complaintId);
    db()->update('complaints', [
        'deleted_by' => Auth::id(),
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $complaintId]);

    db()->commit();

    log_activity(Auth::id(), 'delete', 'complaints', "Deleted complaint: {$complaint['ticket_no']} - {$complaint['subject']}", ['complaint_id' => $complaintId]);

    if (isAjax()) {
        json_success([], 'Complaint deleted successfully.');
    }
    alert_success('Complaint deleted successfully.');
} catch (Exception $e) {
    db()->rollback();
    error_log("Complaint delete error: " . $e->getMessage());
    alert_error('Failed to delete complaint.');
}

redirect(ADMIN_URL . 'complaints/index.php');

<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('complaints.assign');

if (!isPost()) {
    redirect(ADMIN_URL . 'complaints/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaintId = (int) post('complaint_id', 0);
$assignedTo = (int) post('assigned_to', 0);

if ($complaintId <= 0 || $assignedTo <= 0) {
    alert_error('Invalid complaint or user ID.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaint = db()->fetchOne("SELECT id, ticket_no, status FROM complaints WHERE id = ? AND deleted_at IS NULL", [$complaintId]);
if (!$complaint) {
    alert_error('Complaint not found.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

if (!in_array($complaint['status'], ['open', 'reopened'])) {
    alert_error('Complaint can only be assigned when status is Open or Reopened.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$user = db()->fetchOne("SELECT id, name FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$assignedTo]);
if (!$user) {
    alert_error('Selected user not found or inactive.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

try {
    db()->beginTransaction();

    db()->update('complaints', [
        'assigned_to' => $assignedTo,
        'assigned_at' => date('Y-m-d H:i:s'),
        'status' => 'in_progress',
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $complaintId]);

    db()->insert('complaint_updates', [
        'complaint_id' => $complaintId,
        'user_id' => Auth::id(),
        'status' => 'in_progress',
        'message' => "Complaint assigned to {$user['name']}.",
        'is_public' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    db()->commit();

    log_activity(Auth::id(), 'assign', 'complaints', "Assigned complaint {$complaint['ticket_no']} to {$user['name']}", [
        'complaint_id' => $complaintId,
        'assigned_to' => $assignedTo
    ]);

    alert_success("Complaint assigned to {$user['name']} successfully.");
} catch (Exception $e) {
    db()->rollback();
    error_log("Complaint assign error: " . $e->getMessage());
    alert_error('Failed to assign complaint.');
}

$referrer = $_SERVER['HTTP_REFERER'] ?? ADMIN_URL . 'complaints/index.php';
redirect($referrer);

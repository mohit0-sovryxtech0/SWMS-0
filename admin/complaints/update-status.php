<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('complaints.resolve');

if (!isPost()) {
    redirect(ADMIN_URL . 'complaints/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaintId = (int) post('complaint_id', 0);
$newStatus = post('status', '');
$message = trim(post('message', ''));

if ($complaintId <= 0 || !$newStatus) {
    alert_error('Invalid parameters.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaint = db()->fetchOne("SELECT * FROM complaints WHERE id = ? AND deleted_at IS NULL", [$complaintId]);
if (!$complaint) {
    alert_error('Complaint not found.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$validTransitions = [
    'open' => ['in_progress'],
    'in_progress' => ['resolved'],
    'resolved' => ['closed'],
    'closed' => ['reopened'],
    'reopened' => ['in_progress'],
];

$currentStatus = $complaint['status'];
if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
    alert_error("Cannot change status from '" . get_status_text($currentStatus) . "' to '" . get_status_text($newStatus) . "'.");
    redirect(ADMIN_URL . 'complaints/view.php?id=' . $complaintId);
}

if (empty($message) && $newStatus !== 'closed') {
    alert_error('Message is required for this status change.');
    redirect(ADMIN_URL . 'complaints/view.php?id=' . $complaintId);
}

try {
    db()->beginTransaction();

    $updateData = [
        'status' => $newStatus,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($newStatus === 'resolved') {
        $updateData['resolved_at'] = date('Y-m-d H:i:s');
        $updateData['resolution_notes'] = $message ?: null;
    }

    if ($newStatus === 'closed') {
        $updateData['closed_by'] = Auth::id();
        $updateData['closing_notes'] = $message ?: null;
    }

    if ($newStatus === 'reopened') {
        $updateData['assigned_to'] = null;
        $updateData['assigned_at'] = null;
        $updateData['resolved_at'] = null;
        $updateData['resolution_notes'] = null;
    }

    db()->update('complaints', $updateData, 'id = :id', ['id' => $complaintId]);

    $attachment = '';
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = UPLOADS_PATH . 'complaints/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = uniqid() . '_' . time() . '.' . strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
            $attachment = $fileName;
        }
    }

    db()->insert('complaint_updates', [
        'complaint_id' => $complaintId,
        'user_id' => Auth::id(),
        'status' => $newStatus,
        'message' => $message ?: "Status changed to " . get_status_text($newStatus) . ".",
        'is_public' => 1,
        'attachment' => $attachment ?: null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    db()->commit();

    log_activity(Auth::id(), 'status_change', 'complaints', "Complaint {$complaint['ticket_no']} status changed from {$currentStatus} to {$newStatus}", [
        'complaint_id' => $complaintId,
        'old_status' => $currentStatus,
        'new_status' => $newStatus,
    ]);

    // Notification placeholder
    // @todo: Send email/SMS notification to citizen/assignee about status change

    alert_success("Complaint status updated to '" . get_status_text($newStatus) . "' successfully.");
} catch (Exception $e) {
    db()->rollback();
    error_log("Complaint status update error: " . $e->getMessage());
    alert_error('Failed to update complaint status.');
}

$referrer = $_SERVER['HTTP_REFERER'] ?? ADMIN_URL . 'complaints/index.php';
redirect($referrer);

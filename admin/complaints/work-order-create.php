<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('workorders.create');

if (!isPost()) {
    redirect(ADMIN_URL . 'complaints/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaintId = (int) post('complaint_id', 0);
$title = trim(post('title', ''));
$description = trim(post('description', ''));
$assignedTo = (int) post('assigned_to', 0);
$priority = post('priority', 'medium');
$startDate = post('start_date', date('Y-m-d'));
$endDate = post('end_date', '');

if (empty($title)) {
    alert_error('Work order title is required.');
    redirect(ADMIN_URL . 'complaints/view.php?id=' . $complaintId);
}

if ($complaintId > 0) {
    $complaint = db()->fetchOne("SELECT id, ticket_no, subject FROM complaints WHERE id = ? AND deleted_at IS NULL", [$complaintId]);
    if (!$complaint) {
        alert_error('Complaint not found.');
        redirect(ADMIN_URL . 'complaints/index.php');
    }
}

$workOrderNo = 'WO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

try {
    db()->beginTransaction();

    $woId = db()->insert('work_orders', [
        'complaint_id' => $complaintId ?: null,
        'work_order_no' => $workOrderNo,
        'title' => $title,
        'description' => $description ?: null,
        'assigned_to' => $assignedTo ?: null,
        'assigned_by' => Auth::id(),
        'assigned_at' => date('Y-m-d H:i:s'),
        'start_date' => $startDate ?: null,
        'end_date' => $endDate ?: null,
        'priority' => $priority,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    if ($complaintId) {
        db()->insert('complaint_updates', [
            'complaint_id' => $complaintId,
            'user_id' => Auth::id(),
            'status' => $complaint['status'],
            'message' => "Work order created: {$workOrderNo} - {$title}",
            'is_public' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    db()->commit();

    log_activity(Auth::id(), 'create', 'workorders', "Created work order: {$workOrderNo} - {$title}", [
        'work_order_id' => $woId,
        'complaint_id' => $complaintId,
    ]);

    alert_success("Work order {$workOrderNo} created successfully.");
} catch (Exception $e) {
    db()->rollback();
    error_log("Work order create error: " . $e->getMessage());
    alert_error('Failed to create work order.');
}

$referrer = $_SERVER['HTTP_REFERER'] ?? ADMIN_URL . 'complaints/index.php';
redirect($referrer);

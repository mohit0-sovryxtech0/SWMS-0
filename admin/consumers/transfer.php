<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('consumers.edit');

$consumerId = (int) post('consumer_id', 0);

if (!isPost()) {
    redirect(ADMIN_URL . 'consumers/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$consumer = db()->fetchOne("SELECT id, consumer_no, full_name FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);
if (!$consumer) {
    alert_error('Consumer not found.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$data = [
    'new_owner_name' => trim(post('new_owner_name', '')),
    'new_owner_mobile' => trim(post('new_owner_mobile', '')),
    'new_owner_email' => trim(post('new_owner_email', '')),
    'reason' => trim(post('reason', '')),
    'transfer_date' => post('transfer_date', date('Y-m-d')),
];

$v = validator($data, [
    'new_owner_name' => 'required|min:2|max:150',
    'new_owner_mobile' => 'required|mobile',
    'new_owner_email' => 'email',
    'reason' => 'required|min:10|max:500',
    'transfer_date' => 'required|date',
]);

if ($v->fails()) {
    alert_error(implode('<br>', $v->allErrors()));
    redirect(ADMIN_URL . 'consumers/view.php?id=' . $consumerId . '#transfer');
}

try {
    db()->beginTransaction();

    $transferId = db()->insert('ownership_transfers', [
        'consumer_id' => $consumerId,
        'previous_owner_name' => $consumer['full_name'],
        'previous_owner_mobile' => db()->fetchColumn("SELECT mobile FROM consumers WHERE id = ?", [$consumerId]),
        'new_owner_name' => $data['new_owner_name'],
        'new_owner_mobile' => $data['new_owner_mobile'],
        'new_owner_email' => $data['new_owner_email'] ?: null,
        'reason' => $data['reason'],
        'transfer_date' => $data['transfer_date'],
        'status' => 'pending',
        'requested_by' => Auth::id(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    db()->insert('consumer_history', [
        'consumer_id' => $consumerId,
        'action' => 'transfer_requested',
        'old_value' => json_encode(['full_name' => $consumer['full_name']]),
        'new_value' => json_encode($data),
        'changed_by' => Auth::id(),
        'changed_at' => date('Y-m-d H:i:s'),
    ]);

    db()->commit();

    log_activity(Auth::id(), 'transfer', 'consumers', "Ownership transfer requested for {$consumer['consumer_no']} to {$data['new_owner_name']}", [
        'consumer_id' => $consumerId,
        'transfer_id' => $transferId
    ]);

    alert_success('Ownership transfer request submitted successfully. Pending approval.');
} catch (Exception $e) {
    db()->rollback();
    error_log("Transfer error: " . $e->getMessage());
    alert_error('Failed to process transfer. Please try again.');
}

redirect(ADMIN_URL . 'consumers/view.php?id=' . $consumerId . '#transfer');

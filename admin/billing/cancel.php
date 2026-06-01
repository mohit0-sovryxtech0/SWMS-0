<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('bills.cancel');

if (!isPost()) {
    redirect(ADMIN_URL . 'billing/index.php');
}

try {
    if (!verify_csrf(post('csrf_token'))) {
        throw new Exception('Security validation failed');
    }

    $billId = intval(post('id'));
    $cancelReason = trim(post('cancel_reason'));

    if (!$billId) {
        throw new Exception('Invalid bill ID');
    }

    if (empty($cancelReason)) {
        throw new Exception('Cancellation reason is required');
    }

    $bill = db()->fetchOne(
        "SELECT b.* FROM bills b WHERE b.id = ? AND b.deleted_at IS NULL",
        [$billId]
    );

    if (!$bill) {
        throw new Exception('Bill not found');
    }

    if ($bill['status'] === 'cancelled') {
        throw new Exception('Bill is already cancelled');
    }

    if ($bill['status'] === 'paid') {
        throw new Exception('Cannot cancel a paid bill. Process a refund instead.');
    }

    db()->beginTransaction();

    try {
        db()->update('bills', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => $cancelReason
        ], 'id = :id', ['id' => $billId]);

        // Release any bill_payments
        $billPayments = db()->fetchAll(
            "SELECT bp.id, bp.payment_id, bp.amount FROM bill_payments bp WHERE bp.bill_id = ?",
            [$billId]
        );
        foreach ($billPayments as $bp) {
            db()->delete('bill_payments', 'id = :id', ['id' => $bp['id']]);
        }

        log_activity(Auth::id(), 'cancel_bill', 'billing', "Cancelled bill {$bill['bill_no']}", [
            'bill_id' => $billId,
            'reason' => $cancelReason
        ]);

        db()->commit();

        set_flash('success', 'Bill ' . escape($bill['bill_no']) . ' has been cancelled');
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }
} catch (Exception $e) {
    set_flash('error', $e->getMessage());
}

$referer = $_SERVER['HTTP_REFERER'] ?? ADMIN_URL . 'billing/index.php';
redirect($referer);

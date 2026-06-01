<?php
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . 'BillingEngine.php';

if (!citizenLoggedIn()) {
    redirect(CITIZEN_URL . 'login.php');
}

$consumerId = citizenId();
$paymentId = intval(get('payment_id', 0));
$billId = intval(get('bill_id', 0));
$print = get('print', '') === '1';

if ($paymentId) {
    $payment = db()->fetchOne(
        "SELECT p.*, c.consumer_no, c.full_name, c.mobile, c.email, c.ward_no, c.tole
         FROM payments p
         JOIN consumers c ON p.consumer_id = c.id
         WHERE p.id = ? AND p.consumer_id = ? AND p.status = 'completed'",
        [$paymentId, $consumerId]
    );
    if (!$payment) {
        alert_error('Payment receipt not found');
        redirect(CITIZEN_URL . 'payment-history.php');
    }

    $allocatedBills = db()->fetchAll(
        "SELECT b.bill_no, b.billing_period_start, b.billing_period_end, b.total_amount, bp.amount AS paid_amount
         FROM bill_payments bp
         JOIN bills b ON bp.bill_id = b.id
         WHERE bp.payment_id = ?",
        [$paymentId]
    );
} elseif ($billId) {
    $bill = db()->fetchOne(
        "SELECT b.*, c.consumer_no, c.full_name, c.mobile, c.ward_no, c.tole
         FROM bills b JOIN consumers c ON b.consumer_id = c.id
         WHERE b.id = ? AND b.consumer_id = ? AND b.deleted_at IS NULL",
        [$billId, $consumerId]
    );
    if (!$bill) {
        alert_error('Bill not found');
        redirect(CITIZEN_URL . 'bills.php');
    }
} else {
    redirect(CITIZEN_URL . 'bills.php');
}

$pageTitle = $paymentId ? 'Payment Receipt' : 'Bill Receipt';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => CITIZEN_URL . 'dashboard.php'],
    ['label' => $paymentId ? 'Payment History' : 'Bills', 'url' => CITIZEN_URL . ($paymentId ? 'payment-history.php' : 'bills.php')],
    ['label' => $pageTitle]
];

if ($print) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= $pageTitle ?> - <?= APP_SHORT ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Courier New', monospace; font-size: 14px; padding: 20px; }
            .receipt-box { max-width: 400px; margin: 0 auto; border: 1px dashed #333; padding: 20px; }
            .receipt-header { text-align: center; border-bottom: 1px dashed #333; padding-bottom: 15px; margin-bottom: 15px; }
            .receipt-header h3 { margin: 0; font-size: 18px; font-weight: bold; }
            .receipt-header p { margin: 2px 0; font-size: 12px; }
            .receipt-row { display: flex; justify-content: space-between; padding: 4px 0; }
            .receipt-label { font-weight: bold; }
            .receipt-total { border-top: 2px double #333; margin-top: 10px; padding-top: 10px; font-size: 16px; font-weight: bold; }
            .receipt-footer { text-align: center; margin-top: 20px; font-size: 11px; border-top: 1px dashed #333; padding-top: 10px; }
            @media print { body { padding: 0; } .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="receipt-box">
            <div class="receipt-header">
                <h3><?= APP_ORG ?></h3>
                <p><?= APP_ADDRESS ?></p>
                <p><?= APP_PHONE ?></p>
                <hr style="border-top:1px dashed #333;">
                <?php if ($paymentId): ?>
                <h4>PAYMENT RECEIPT</h4>
                <p>Receipt No: <strong><?= escape($payment['receipt_no']) ?></strong></p>
                <?php else: ?>
                <h4>BILL RECEIPT</h4>
                <p>Bill No: <strong><?= escape($bill['bill_no']) ?></strong></p>
                <?php endif; ?>
            </div>

            <div class="receipt-row"><span class="receipt-label">Consumer No:</span><span><?= escape($payment['consumer_no'] ?? $bill['consumer_no']) ?></span></div>
            <div class="receipt-row"><span class="receipt-label">Consumer Name:</span><span><?= escape($payment['full_name'] ?? $bill['full_name']) ?></span></div>
            <div class="receipt-row"><span class="receipt-label">Mobile:</span><span><?= escape($payment['mobile'] ?? $bill['mobile']) ?></span></div>
            <div class="receipt-row"><span class="receipt-label">Address:</span><span>Ward <?= escape($payment['ward_no'] ?? $bill['ward_no']) ?>, <?= escape($payment['tole'] ?? $bill['tole'] ?? '') ?></span></div>

            <?php if ($paymentId): ?>
            <div style="border-top:1px dashed #333; margin:10px 0; padding-top:10px;">
                <strong>Payment Details</strong>
                <div class="receipt-row"><span>Payment Method:</span><span><?= escape(ucfirst($payment['payment_method'])) ?></span></div>
                <div class="receipt-row"><span>Payment Date:</span><span><?= format_date($payment['payment_date']) ?></span></div>
                <div class="receipt-row"><span>Transaction ID:</span><span><?= escape($payment['transaction_id'] ?? '-') ?></span></div>
                <?php if (!empty($allocatedBills)): ?>
                <div style="margin-top:8px;"><strong>Bill Details</strong></div>
                <?php foreach ($allocatedBills as $ab): ?>
                <div class="receipt-row"><span><?= escape($ab['bill_no']) ?></span><span>NRs. <?= number_format($ab['paid_amount'], 2) ?></span></div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($payment['discount'] > 0): ?>
                <div class="receipt-row"><span>Discount:</span><span>-NRs. <?= number_format($payment['discount'], 2) ?></span></div>
                <?php endif; ?>
                <?php if ($payment['penalty_waived'] > 0): ?>
                <div class="receipt-row"><span>Penalty Waived:</span><span>-NRs. <?= number_format($payment['penalty_waived'], 2) ?></span></div>
                <?php endif; ?>
                <div class="receipt-row receipt-total"><span>Net Paid:</span><span>NRs. <?= number_format($payment['net_amount'], 2) ?></span></div>
            </div>
            <div class="receipt-row" style="margin-top:8px;font-size:12px;color:#666;">
                <span>Amount in Words:</span>
            </div>
            <div style="font-size:12px;color:#666;font-style:italic;">
                <?= BillingEngine::numberToWords($payment['net_amount']) ?>
            </div>
            <?php else: ?>
            <div style="border-top:1px dashed #333; margin:10px 0; padding-top:10px;">
                <strong>Bill Details</strong>
                <div class="receipt-row"><span>Period:</span><span><?= format_date($bill['billing_period_start']) ?> - <?= format_date($bill['billing_period_end']) ?></span></div>
                <div class="receipt-row"><span>Due Date:</span><span><?= format_date($bill['due_date']) ?></span></div>
                <div class="receipt-row"><span>Previous Reading:</span><span><?= number_format($bill['previous_reading'], 2) ?></span></div>
                <div class="receipt-row"><span>Current Reading:</span><span><?= number_format($bill['current_reading'], 2) ?></span></div>
                <div class="receipt-row"><span>Consumption:</span><span><?= number_format($bill['consumption'], 2) ?> Units</span></div>
                <div class="receipt-row receipt-total"><span>Total Amount:</span><span>NRs. <?= number_format($bill['total_amount'], 2) ?></span></div>
            </div>
            <?php if ($bill['paid_amount'] > 0): ?>
            <div class="receipt-row"><span>Paid Amount:</span><span>NRs. <?= number_format($bill['paid_amount'], 2) ?></span></div>
            <div class="receipt-row"><span>Due Amount:</span><span>NRs. <?= number_format($bill['due_amount'], 2) ?></span></div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="receipt-footer">
                <p>Thank you for your payment. This is a system-generated receipt.</p>
                <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
                <p style="font-size:10px;"><?= APP_SHORT ?> - <?= APP_ORG ?></p>
            </div>
        </div>
        <div class="text-center mt-3 no-print">
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
            <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
        </div>
        <script>window.onload = function() { window.print(); };</script>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h4><i class="fas fa-receipt me-2 text-primary"></i><?= $pageTitle ?></h4>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <?php if ($paymentId): ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success fa-4x mb-2"></i>
                        <h5>Payment Successful</h5>
                        <p class="text-muted">Receipt #<?= escape($payment['receipt_no']) ?></p>
                    </div>
                    <table class="table table-borderless mb-0">
                        <tr><td class="text-muted">Consumer</td><td class="fw-semibold"><?= escape($payment['full_name']) ?> (<?= escape($payment['consumer_no']) ?>)</td></tr>
                        <tr><td class="text-muted">Payment Method</td><td><span class="badge bg-info"><?= escape(ucfirst($payment['payment_method'])) ?></span></td></tr>
                        <tr><td class="text-muted">Payment Date</td><td><?= format_date($payment['payment_date']) ?></td></tr>
                        <tr><td class="text-muted">Transaction ID</td><td class="text-muted"><?= escape($payment['transaction_id'] ?? '-') ?></td></tr>
                        <?php if (!empty($allocatedBills)): ?>
                        <tr><td class="text-muted">Bills Paid</td><td>
                            <?php foreach ($allocatedBills as $ab): ?>
                            <div><?= escape($ab['bill_no']) ?> — NRs. <?= number_format($ab['paid_amount'], 2) ?></div>
                            <?php endforeach; ?>
                        </td></tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Total Amount</td><td><strong>NRs. <?= number_format($payment['amount'], 2) ?></strong></td></tr>
                        <?php if ($payment['discount'] > 0): ?>
                        <tr><td class="text-muted">Discount</td><td class="text-success">-NRs. <?= number_format($payment['discount'], 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($payment['penalty_waived'] > 0): ?>
                        <tr><td class="text-muted">Penalty Waived</td><td class="text-success">-NRs. <?= number_format($payment['penalty_waived'], 2) ?></td></tr>
                        <?php endif; ?>
                        <tr class="border-top"><td class="fw-bold fs-5">Net Paid</td><td class="fw-bold fs-5 text-success">NRs. <?= number_format($payment['net_amount'], 2) ?></td></tr>
                    </table>
                    <p class="text-muted small mt-2"><em><?= BillingEngine::numberToWords($payment['net_amount']) ?></em></p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="?payment_id=<?= $paymentId ?>&print=1" class="btn btn-primary" target="_blank">
                            <i class="fas fa-print me-1"></i>Print Receipt
                        </a>
                        <a href="?payment_id=<?= $paymentId ?>&print=1" class="btn btn-outline-primary" download>
                            <i class="fas fa-download me-1"></i>Download PDF
                        </a>
                    </div>
                    <?php elseif ($billId): ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-file-invoice text-primary fa-4x mb-2"></i>
                        <h5>Bill #<?= escape($bill['bill_no']) ?></h5>
                        <span class="badge bg-<?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'overdue' ? 'danger' : 'warning') ?> fs-6">
                            <?= ucfirst($bill['status']) ?>
                        </span>
                    </div>
                    <table class="table table-borderless mb-0">
                        <tr><td class="text-muted">Consumer</td><td class="fw-semibold"><?= escape($bill['full_name']) ?> (<?= escape($bill['consumer_no']) ?>)</td></tr>
                        <tr><td class="text-muted">Billing Period</td><td><?= format_date($bill['billing_period_start']) ?> to <?= format_date($bill['billing_period_end']) ?></td></tr>
                        <tr><td class="text-muted">Due Date</td><td class="<?= strtotime($bill['due_date']) < time() && $bill['status'] !== 'paid' ? 'text-danger fw-bold' : '' ?>"><?= format_date($bill['due_date']) ?></td></tr>
                        <tr><td class="text-muted">Previous Reading</td><td><?= number_format($bill['previous_reading'], 2) ?></td></tr>
                        <tr><td class="text-muted">Current Reading</td><td><?= number_format($bill['current_reading'], 2) ?></td></tr>
                        <tr><td class="text-muted">Consumption</td><td><strong><?= number_format($bill['consumption'], 2) ?></strong> Units</td></tr>
                    </table>
                    <hr>
                    <h6 class="fw-bold">Charge Breakdown</h6>
                    <table class="table table-borderless mb-0">
                        <tr><td>Base Fee</td><td class="text-end"><?= format_currency($bill['base_fee']) ?></td></tr>
                        <tr><td>Consumption Charge</td><td class="text-end"><?= format_currency($bill['consumption_charge']) ?></td></tr>
                        <tr><td>Meter Rent</td><td class="text-end"><?= format_currency($bill['meter_rent']) ?></td></tr>
                        <tr><td>Sewerage Fee</td><td class="text-end"><?= format_currency($bill['sewerage_fee']) ?></td></tr>
                        <?php if ($bill['vat_amount'] > 0): ?>
                        <tr><td>VAT</td><td class="text-end"><?= format_currency($bill['vat_amount']) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($bill['penalty_amount'] > 0): ?>
                        <tr><td class="text-danger">Penalty</td><td class="text-end text-danger"><?= format_currency($bill['penalty_amount']) ?></td></tr>
                        <?php endif; ?>
                        <tr class="border-top fw-bold fs-5">
                            <td>Total Amount</td>
                            <td class="text-end"><?= format_currency($bill['total_amount']) ?></td>
                        </tr>
                        <?php if ($bill['paid_amount'] > 0): ?>
                        <tr><td class="text-success">Paid Amount</td><td class="text-end text-success"><?= format_currency($bill['paid_amount']) ?></td></tr>
                        <tr class="fw-bold"><td>Due Amount</td><td class="text-end <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($bill['due_amount']) ?></td></tr>
                        <?php endif; ?>
                    </table>
                    <div class="d-flex gap-2 mt-3">
                        <a href="?bill_id=<?= $billId ?>&print=1" class="btn btn-primary" target="_blank">
                            <i class="fas fa-print me-1"></i>Print Bill
                        </a>
                        <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
                        <a href="<?= CITIZEN_URL ?>payment-callback.php?bill_id=<?= $bill['id'] ?>" class="btn btn-success">
                            <i class="fas fa-credit-card me-1"></i>Pay Now
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

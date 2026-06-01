<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'My Bills';
if (!isset($_SESSION['citizen_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(CITIZEN_URL . 'login.php');
}
require_once __DIR__ . '/includes/header.php';

$consumerId = citizenId();
$db = db();
$billId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle payment action
if (isset($_GET['pay']) && $billId > 0) {
    $bill = $db->fetchOne("SELECT id, bill_no, total_amount, due_amount, status FROM bills WHERE id = ? AND consumer_id = ? AND deleted_at IS NULL", [$billId, $consumerId]);
    if ($bill && $bill['status'] !== 'paid' && $bill['status'] !== 'cancelled') {
        $_SESSION['payment_pending'] = [
            'bill_ids' => [$bill['id']],
            'consumer_id' => $consumerId,
            'amount' => $bill['due_amount']
        ];
        redirect(CITIZEN_URL . 'payment-callback.php');
    }
}

// View single bill detail
if ($billId > 0) {
    $bill = $db->fetchOne("
        SELECT b.*, t.name as tariff_name, m.meter_no,
               (SELECT COUNT(*) FROM payments p JOIN bill_payments bp ON p.id = bp.payment_id WHERE bp.bill_id = b.id AND p.status = 'completed') as payment_count
        FROM bills b
        LEFT JOIN tariffs t ON b.tariff_id = t.id
        LEFT JOIN meters m ON b.meter_id = m.id
        WHERE b.id = ? AND b.consumer_id = ? AND b.deleted_at IS NULL
    ", [$billId, $consumerId]);

    if (!$bill) {
        alert_error('Bill not found.');
        redirect(CITIZEN_URL . 'bills.php');
    }

    // Mark bill as read
    $db->update('bills', ['is_read' => 1], 'id = :id', ['id' => $billId]);
?>
<div class="page-header">
    <div class="container">
        <h2><i class="fas fa-file-invoice me-2"></i> Bill Detail</h2>
        <p>Bill #<?= escape($bill['bill_no']) ?></p>
    </div>
</div>
<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card bill-detail-card">
                <div class="bill-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="bill-no"><?= APP_ORG ?></div>
                            <div class="bill-no">Bill No: <?= escape($bill['bill_no']) ?></div>
                        </div>
                        <div class="col-auto text-end">
                            <div class="bill-amount"><?= format_currency($bill['total_amount']) ?></div>
                            <div class="bill-no"><?= get_status_badge($bill['status']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="bill-body">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Billing Period</small>
                            <strong><?= format_date($bill['billing_period_start']) ?> to <?= format_date($bill['billing_period_end']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Due Date</small>
                            <strong class="<?= strtotime($bill['due_date']) < time() && $bill['status'] !== 'paid' ? 'text-danger' : '' ?>">
                                <?= format_date($bill['due_date']) ?>
                                <?= strtotime($bill['due_date']) < time() && $bill['status'] !== 'paid' ? '(Overdue)' : '' ?>
                            </strong>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Meter No</small>
                            <strong><?= escape($bill['meter_no'] ?? 'N/A') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Tariff</small>
                            <strong><?= escape($bill['tariff_name'] ?? 'Standard') ?></strong>
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3">Consumption Details</h6>
                    <div class="bill-row"><span class="label">Previous Reading</span><span class="value"><?= number_format($bill['previous_reading'], 2) ?></span></div>
                    <div class="bill-row"><span class="label">Current Reading</span><span class="value"><?= number_format($bill['current_reading'], 2) ?></span></div>
                    <div class="bill-row"><span class="label">Consumption (Units)</span><span class="value"><?= number_format($bill['consumption'], 2) ?></span></div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Charge Breakdown</h6>
                    <div class="bill-row"><span class="label">Base Fee</span><span class="value"><?= format_currency($bill['base_fee']) ?></span></div>
                    <div class="bill-row"><span class="label">Consumption Charge</span><span class="value"><?= format_currency($bill['consumption_charge']) ?></span></div>
                    <div class="bill-row"><span class="label">Meter Rent</span><span class="value"><?= format_currency($bill['meter_rent']) ?></span></div>
                    <div class="bill-row"><span class="label">Sewerage Fee</span><span class="value"><?= format_currency($bill['sewerage_fee']) ?></span></div>
                    <?php if ($bill['vat_amount'] > 0): ?>
                    <div class="bill-row"><span class="label">VAT (13%)</span><span class="value"><?= format_currency($bill['vat_amount']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($bill['penalty_amount'] > 0): ?>
                    <div class="bill-row"><span class="label text-danger">Penalty</span><span class="value text-danger"><?= format_currency($bill['penalty_amount']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($bill['discount_amount'] > 0): ?>
                    <div class="bill-row"><span class="label text-success">Discount</span><span class="value text-success">-<?= format_currency($bill['discount_amount']) ?></span></div>
                    <?php endif; ?>
                    <div class="bill-row fw-bold fs-5" style="border-top:2px solid var(--gray-900);padding-top:12px;">
                        <span class="label">Total Amount</span>
                        <span class="value"><?= format_currency($bill['total_amount']) ?></span>
                    </div>
                    <?php if ($bill['paid_amount'] > 0): ?>
                    <div class="bill-row">
                        <span class="label text-success">Paid Amount</span>
                        <span class="value text-success"><?= format_currency($bill['paid_amount']) ?></span>
                    </div>
                    <div class="bill-row fw-bold">
                        <span class="label">Due Amount</span>
                        <span class="value <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($bill['due_amount']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white d-flex gap-2 justify-content-end p-3">
                    <a href="<?= CITIZEN_URL ?>receipt.php?bill_id=<?= $bill['id'] ?>&print=1" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i> Print / PDF</a>
                    <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
                        <a href="<?= CITIZEN_URL ?>payment-callback.php?bill_id=<?= $bill['id'] ?>" class="btn btn-primary"><i class="fas fa-credit-card me-1"></i> Pay Now</a>
                    <?php endif; ?>
                    <a href="<?= CITIZEN_URL ?>bills.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
} else {
    // Bills list
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = RECORDS_PER_PAGE;
    $offset = ($page - 1) * $perPage;

    $totalBills = $db->fetchColumn("SELECT COUNT(*) FROM bills WHERE consumer_id = ? AND deleted_at IS NULL", [$consumerId]);
    $bills = $db->fetchAll(
        "SELECT id, bill_no, billing_period_start, billing_period_end, total_amount, paid_amount, due_amount, status, due_date, created_at
         FROM bills WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$consumerId, $perPage, $offset]
    );
?>
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col">
                <h2><i class="fas fa-file-invoice me-2"></i> My Bills</h2>
                <p>View and pay your water bills</p>
            </div>
            <div class="col-auto">
                <span class="badge bg-light text-dark px-3 py-2"><?= $totalBills ?> Total Bills</span>
            </div>
        </div>
    </div>
</div>
<div class="container pb-5">
    <?php if (empty($bills)): ?>
        <div class="text-center py-5">
            <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No bills found</h5>
            <p>Your bills will appear here once generated.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-citizen">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Period</th>
                        <th>Total</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $b): ?>
                    <tr>
                        <td class="fw-semibold"><?= escape($b['bill_no']) ?></td>
                        <td><?= format_date($b['billing_period_start'], 'd M Y') ?> - <?= format_date($b['billing_period_end'], 'd M Y') ?></td>
                        <td><?= format_currency($b['total_amount']) ?></td>
                        <td class="<?= $b['due_amount'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= format_currency($b['due_amount']) ?></td>
                        <td><?= get_status_badge($b['status']) ?></td>
                        <td>
                            <?= format_date($b['due_date']) ?>
                            <?php if (strtotime($b['due_date']) < time() && $b['status'] !== 'paid'): ?>
                                <br><small class="text-danger fw-semibold">Overdue</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= CITIZEN_URL ?>receipt.php?bill_id=<?= $b['id'] ?>" class="btn btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                <a href="<?= CITIZEN_URL ?>receipt.php?bill_id=<?= $b['id'] ?>&print=1" class="btn btn-outline-secondary" title="Print/PDF" target="_blank"><i class="fas fa-print"></i></a>
                                <?php if ($b['status'] !== 'paid' && $b['status'] !== 'cancelled'): ?>
                                <a href="<?= CITIZEN_URL ?>payment-callback.php?bill_id=<?= $b['id'] ?>" class="btn btn-success btn-sm" title="Pay"><i class="fas fa-credit-card"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($totalBills, $page, $perPage, CITIZEN_URL . 'bills.php?page={page}') ?>
    <?php endif; ?>
</div>
<?php } ?>
<?php include_once __DIR__ . '/includes/footer.php'; ?>

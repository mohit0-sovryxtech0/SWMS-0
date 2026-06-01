<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('bills.view');

$pageTitle = 'View Bill';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'View Bill']
];

$billId = intval(get('id'));
if (!$billId) {
    redirect(ADMIN_URL . 'billing/index.php');
}

$bill = db()->fetchOne(
    "SELECT b.*, c.full_name, c.consumer_no, c.mobile, c.email, c.address,
            c.ward_no, c.tole, c.permanent_address, c.permanent_ward,
            m.meter_no, fy.label as fiscal_year_label, fy.year_code as fiscal_year_code,
            t.name as tariff_name, u.name as generated_by_name
     FROM bills b
     JOIN consumers c ON b.consumer_id = c.id
     LEFT JOIN meters m ON b.meter_id = m.id
     LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
     LEFT JOIN tariffs t ON b.tariff_id = t.id
     LEFT JOIN users u ON b.generated_by = u.id
     WHERE b.id = ?",
    [$billId]
);

if (!$bill) {
    set_flash('error', 'Bill not found');
    redirect(ADMIN_URL . 'billing/index.php');
}

$payments = db()->fetchAll(
    "SELECT p.*, bp.amount as allocated_amount, u.name as received_by_name
     FROM payments p
     JOIN bill_payments bp ON p.id = bp.payment_id AND bp.bill_id = ?
     LEFT JOIN users u ON p.received_by = u.id
     ORDER BY p.payment_date DESC",
    [$billId]
);

$organization = db()->fetchOne("SELECT * FROM organizations LIMIT 1");

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-file-invoice me-2 text-primary"></i>Bill: <?= escape($bill['bill_no']) ?>
        </h4>
        <div class="btn-group">
            <?php if (RBAC::can('bills.edit') && $bill['status'] !== 'cancelled'): ?>
            <a href="<?= ADMIN_URL ?>billing/edit.php?id=<?= $billId ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i>Edit
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('payments.record') && in_array($bill['status'], ['pending', 'partial', 'overdue'])): ?>
            <a href="<?= ADMIN_URL ?>billing/record-payment.php?consumer_id=<?= $bill['consumer_id'] ?>" class="btn btn-success">
                <i class="fas fa-hand-holding-usd me-1"></i>Record Payment
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('bills.cancel') && $bill['status'] !== 'cancelled' && $bill['status'] !== 'paid'): ?>
            <button type="button" class="btn btn-danger" onclick="showCancelModal(<?= $billId ?>)">
                <i class="fas fa-ban me-1"></i>Cancel Bill
            </button>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>billing/receipt.php?bill_id=<?= $billId ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print me-1"></i>Print
            </a>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Bill Header & Consumer Info -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <?php if ($organization): ?>
                        <h5 class="mb-1"><?= escape($organization['name']) ?></h5>
                        <p class="mb-0 small text-muted">
                            <?= escape($organization['address']) ?><br>
                            PAN: <?= escape($organization['pan_no'] ?? 'N/A') ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <h3 class="text-primary mb-1"><?= escape($bill['bill_no']) ?></h3>
                        <p class="mb-0 small">
                            Date: <?= format_date($bill['generated_at']) ?><br>
                            Fiscal Year: <?= escape($bill['fiscal_year_label']) ?>
                        </p>
                        <?= get_status_badge($bill['status']) ?>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Consumer Information</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" style="width:120px">Name:</td><td class="fw-semibold"><?= escape($bill['full_name']) ?></td></tr>
                            <tr><td class="text-muted">Consumer No:</td><td><?= escape($bill['consumer_no']) ?></td></tr>
                            <tr><td class="text-muted">Mobile:</td><td><?= escape($bill['mobile']) ?></td></tr>
                            <tr><td class="text-muted">Ward No:</td><td><?= escape($bill['ward_no']) ?></td></tr>
                            <tr><td class="text-muted">Address:</td><td><?= escape($bill['tole'] ?: $bill['address']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Meter Information</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" style="width:120px">Meter No:</td><td><?= escape($bill['meter_no'] ?? 'N/A') ?></td></tr>
                            <tr><td class="text-muted">Previous Reading:</td><td><?= number_format($bill['previous_reading'], 2) ?></td></tr>
                            <tr><td class="text-muted">Current Reading:</td><td><?= number_format($bill['current_reading'], 2) ?></td></tr>
                            <tr><td class="text-muted">Consumption:</td><td class="fw-semibold"><?= number_format($bill['consumption'], 2) ?> Units</td></tr>
                            <tr><td class="text-muted">Billing Period:</td><td><?= format_date($bill['billing_period_start']) ?> to <?= format_date($bill['billing_period_end']) ?></td></tr>
                            <tr><td class="text-muted">Due Date:</td><td class="fw-semibold <?= (strtotime($bill['due_date']) < time() && $bill['status'] != 'paid') ? 'text-danger' : '' ?>"><?= format_date($bill['due_date']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charges Breakdown -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-calculator me-2"></i>Charges Breakdown</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td>Base Fee</td>
                        <td class="text-end"><?= format_currency($bill['base_fee']) ?></td>
                    </tr>
                    <tr>
                        <td>Consumption Charge (<?= number_format($bill['consumption'], 2) ?> units)</td>
                        <td class="text-end"><?= format_currency($bill['consumption_charge']) ?></td>
                    </tr>
                    <tr>
                        <td>Meter Rent</td>
                        <td class="text-end"><?= format_currency($bill['meter_rent']) ?></td>
                    </tr>
                    <tr>
                        <td>Sewerage Fee</td>
                        <td class="text-end"><?= format_currency($bill['sewerage_fee']) ?></td>
                    </tr>
                    <?php if ($bill['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-success">Discount</td>
                        <td class="text-end text-success">-<?= format_currency($bill['discount_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($bill['penalty_amount'] > 0): ?>
                    <tr>
                        <td class="text-danger">Penalty</td>
                        <td class="text-end text-danger"><?= format_currency($bill['penalty_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>VAT</td>
                        <td class="text-end"><?= format_currency($bill['vat_amount']) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold fs-6">Total Amount</td>
                        <td class="text-end fw-bold fs-6"><?= format_currency($bill['total_amount']) ?></td>
                    </tr>
                    <?php if ($bill['paid_amount'] > 0): ?>
                    <tr>
                        <td class="text-success">Paid Amount</td>
                        <td class="text-end text-success"><?= format_currency($bill['paid_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="border-top <?= $bill['due_amount'] > 0 ? 'bg-light' : '' ?>">
                        <td class="fw-bold <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $bill['due_amount'] > 0 ? 'Due Amount' : 'Fully Paid' ?>
                        </td>
                        <td class="text-end fw-bold <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= format_currency($bill['due_amount']) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($bill['remarks']): ?>
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="fw-bold"><i class="fas fa-sticky-note me-2"></i>Remarks</h6>
                <p class="mb-0 small"><?= escape($bill['remarks']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment History -->
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Payment History</h5>
        <?php if (RBAC::can('payments.record') && in_array($bill['status'], ['pending', 'partial', 'overdue'])): ?>
        <a href="<?= ADMIN_URL ?>billing/record-payment.php?consumer_id=<?= $bill['consumer_id'] ?>" class="btn btn-sm btn-success">
            <i class="fas fa-plus me-1"></i>Record Payment
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($payments)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-credit-card fa-3x mb-2"></i>
            <p class="mb-0">No payments recorded for this bill</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th>Received By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pmt): ?>
                    <tr>
                        <td class="fw-semibold"><?= escape($pmt['receipt_no']) ?></td>
                        <td><?= format_date($pmt['payment_date']) ?></td>
                        <td><?= format_currency($pmt['net_amount']) ?></td>
                        <td><?= escape(ucfirst($pmt['payment_method'])) ?></td>
                        <td><?= escape($pmt['transaction_id'] ?? '-') ?></td>
                        <td><?= escape($pmt['received_by_name'] ?? '-') ?></td>
                        <td><?= get_status_badge($pmt['status']) ?></td>
                        <td>
                            <a href="<?= ADMIN_URL ?>billing/receipt.php?payment_id=<?= $pmt['id'] ?>" class="btn btn-sm btn-info" target="_blank" title="View Receipt">
                                <i class="fas fa-receipt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="text-muted small mt-3">
    Generated by <?= escape($bill['generated_by_name'] ?? 'System') ?> on <?= format_datetime($bill['generated_at']) ?>
    <?php if ($bill['cancelled_at']): ?>
    <br>Cancelled on <?= format_datetime($bill['cancelled_at']) ?>
    <?php if ($bill['cancel_reason']): ?> - Reason: <?= escape($bill['cancel_reason']) ?><?php endif; ?>
    <?php endif; ?>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= ADMIN_URL ?>billing/cancel.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="cancelBillId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban text-danger me-2"></i>Cancel Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel bill <strong><?= escape($bill['bill_no']) ?></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="3" required placeholder="Enter cancellation reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban me-1"></i>Cancel Bill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showCancelModal(id) {
    document.getElementById('cancelBillId').value = id;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
</script>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

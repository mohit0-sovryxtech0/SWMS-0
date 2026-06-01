<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('bills.edit');

$pageTitle = 'Edit Bill';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Edit Bill']
];

$billId = intval(get('id'));
if (!$billId) {
    redirect(ADMIN_URL . 'billing/index.php');
}

$bill = db()->fetchOne(
    "SELECT b.*, c.full_name, c.consumer_no
     FROM bills b
     JOIN consumers c ON b.consumer_id = c.id
     WHERE b.id = ?",
    [$billId]
);

if (!$bill) {
    set_flash('error', 'Bill not found');
    redirect(ADMIN_URL . 'billing/index.php');
}

if ($bill['status'] === 'paid' || $bill['status'] === 'cancelled') {
    set_flash('error', 'Cannot edit a ' . $bill['status'] . ' bill');
    redirect(ADMIN_URL . 'billing/view.php?id=' . $billId);
}

$error = '';
$success = '';

if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $data = [
            'base_fee' => floatval(post('base_fee')),
            'consumption_charge' => floatval(post('consumption_charge')),
            'meter_rent' => floatval(post('meter_rent')),
            'sewerage_fee' => floatval(post('sewerage_fee')),
            'vat_amount' => floatval(post('vat_amount')),
            'penalty_amount' => floatval(post('penalty_amount')),
            'discount_amount' => floatval(post('discount_amount')),
            'previous_reading' => floatval(post('previous_reading')),
            'current_reading' => floatval(post('current_reading')),
            'consumption' => floatval(post('consumption')),
            'due_date' => post('due_date'),
            'remarks' => post('remarks')
        ];

        $validator = validator($_POST, [
            'base_fee' => 'required|numeric',
            'consumption_charge' => 'required|numeric',
            'meter_rent' => 'required|numeric',
            'sewerage_fee' => 'required|numeric',
            'vat_amount' => 'required|numeric',
            'due_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->firstError());
        }

        $total = $data['base_fee'] + $data['consumption_charge'] + $data['meter_rent']
               + $data['sewerage_fee'] + $data['vat_amount'] + $data['penalty_amount']
               - $data['discount_amount'];

        if ($total < 0) $total = 0;

        $paidAmount = floatval($bill['paid_amount']);
        $dueAmount = max(0, $total - $paidAmount);

        $data['total_amount'] = $total;
        $data['due_amount'] = $dueAmount;

        if ($dueAmount <= 0 && $paidAmount > 0) {
            $data['status'] = 'paid';
        } elseif ($dueAmount < $total && $paidAmount > 0) {
            $data['status'] = 'partial';
        } elseif ($dueAmount >= $total && strtotime($data['due_date']) < time()) {
            $data['status'] = 'overdue';
        } else {
            $data['status'] = 'pending';
        }

        db()->update('bills', $data, 'id = :id', ['id' => $billId]);

        log_activity(Auth::id(), 'edit_bill', 'billing', "Edited bill {$bill['bill_no']}", [
            'bill_id' => $billId,
            'changes' => $data
        ]);

        set_flash('success', 'Bill updated successfully');
        redirect(ADMIN_URL . 'billing/view.php?id=' . $billId);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-edit me-2 text-warning"></i>Edit Bill: <?= escape($bill['bill_no']) ?>
        </h4>
        <a href="<?= ADMIN_URL ?>billing/view.php?id=<?= $billId ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Editing bill for <strong><?= escape($bill['full_name']) ?></strong> (<?= escape($bill['consumer_no']) ?>)
            - Current Status: <?= get_status_badge($bill['status']) ?>
        </div>

        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <h6 class="fw-bold border-bottom pb-2">Meter Readings</h6>
                <div class="col-md-3">
                    <label class="form-label">Previous Reading</label>
                    <input type="number" step="0.01" name="previous_reading" class="form-control"
                           value="<?= $bill['previous_reading'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Reading</label>
                    <input type="number" step="0.01" name="current_reading" class="form-control"
                           value="<?= $bill['current_reading'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Consumption</label>
                    <input type="number" step="0.01" name="consumption" class="form-control"
                           value="<?= $bill['consumption'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" class="form-control" required
                           value="<?= $bill['due_date'] ?>">
                </div>
            </div>

            <div class="row g-3 mt-2">
                <h6 class="fw-bold border-bottom pb-2">Charges</h6>
                <div class="col-md-3">
                    <label class="form-label">Base Fee <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="base_fee" class="form-control" required
                           value="<?= $bill['base_fee'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Consumption Charge <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="consumption_charge" class="form-control" required
                           value="<?= $bill['consumption_charge'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Meter Rent <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="meter_rent" class="form-control" required
                           value="<?= $bill['meter_rent'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sewerage Fee <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="sewerage_fee" class="form-control" required
                           value="<?= $bill['sewerage_fee'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">VAT Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="vat_amount" class="form-control" required
                           value="<?= $bill['vat_amount'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Penalty</label>
                    <input type="number" step="0.01" name="penalty_amount" class="form-control"
                           value="<?= $bill['penalty_amount'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount</label>
                    <input type="number" step="0.01" name="discount_amount" class="form-control"
                           value="<?= $bill['discount_amount'] ?>">
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"><?= escape($bill['remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning btn-lg">
                    <i class="fas fa-save me-1"></i>Update Bill
                </button>
                <a href="<?= ADMIN_URL ?>billing/view.php?id=<?= $billId ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

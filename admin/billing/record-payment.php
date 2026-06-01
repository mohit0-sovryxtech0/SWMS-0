<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('payments.record');

$pageTitle = 'Record Payment';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Payments', 'url' => ADMIN_URL . 'billing/payments.php'],
    ['label' => 'Record Payment']
];

$selectedConsumerId = intval(get('consumer_id'));
$apiUrl = API_URL;
$adminUrl = ADMIN_URL;

$error = '';
$success = '';

if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $consumerId = intval(post('consumer_id'));
        $billIds = post('bill_ids') ?: [];
        $amount = floatval(post('amount'));
        $discount = floatval(post('discount') ?: 0);
        $penaltyWaived = floatval(post('penalty_waived') ?: 0);
        $netAmount = floatval(post('net_amount'));
        $paymentMethod = post('payment_method');
        $paymentDate = post('payment_date');
        $remarks = post('remarks');
        $bankName = post('bank_name');
        $chequeNo = post('cheque_no');
        $transactionId = post('transaction_id');

        $validator = validator($_POST, [
            'consumer_id' => 'required|numeric',
            'bill_ids' => 'required|array',
            'amount' => 'required|numeric',
            'net_amount' => 'required|numeric',
            'payment_method' => 'required|in:cash,bank,esewa,khalti,fonepay,qr,cheque,online',
            'payment_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->firstError());
        }

        if (empty($billIds)) {
            throw new Exception('Please select at least one bill to pay');
        }

        if ($netAmount <= 0) {
            throw new Exception('Net amount must be greater than zero');
        }

        $consumer = db()->fetchOne("SELECT id, full_name, consumer_no FROM consumers WHERE id = ?", [$consumerId]);
        if (!$consumer) throw new Exception('Consumer not found');

        db()->beginTransaction();

        try {
            $receiptNo = 'RCT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $paymentId = db()->insert('payments', [
                'receipt_no' => $receiptNo,
                'consumer_id' => $consumerId,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'discount' => $discount,
                'penalty_waived' => $penaltyWaived,
                'net_amount' => $netAmount,
                'payment_method' => $paymentMethod,
                'payment_mode' => 'office',
                'bank_name' => $bankName ?: null,
                'cheque_no' => $chequeNo ?: null,
                'transaction_id' => $transactionId ?: null,
                'received_by' => Auth::id(),
                'remarks' => $remarks ?: null,
                'status' => 'completed'
            ]);

            $remaining = $netAmount;

            foreach ($billIds as $bid) {
                if ($remaining <= 0) break;

                $bill = db()->fetchOne(
                    "SELECT id, bill_no, total_amount, paid_amount, due_amount, status
                     FROM bills WHERE id = ? AND consumer_id = ? AND deleted_at IS NULL",
                    [$bid, $consumerId]
                );

                if (!$bill) continue;
                if ($bill['status'] === 'paid' || $bill['status'] === 'cancelled') continue;

                $billDue = floatval($bill['due_amount']);
                $allocAmount = min($remaining, $billDue);

                if ($allocAmount <= 0) continue;

                db()->insert('bill_payments', [
                    'bill_id' => $bid,
                    'payment_id' => $paymentId,
                    'amount' => $allocAmount
                ]);

                $newPaid = floatval($bill['paid_amount']) + $allocAmount;
                $newDue = floatval($bill['total_amount']) - $newPaid;

                if ($newDue <= 0) {
                    $newStatus = 'paid';
                    $paidAt = date('Y-m-d H:i:s');
                } elseif ($newPaid > 0) {
                    $newStatus = 'partial';
                    $paidAt = null;
                } else {
                    $newStatus = 'pending';
                    $paidAt = null;
                }

                db()->update('bills', [
                    'paid_amount' => $newPaid,
                    'due_amount' => $newDue,
                    'status' => $newStatus,
                    'paid_at' => $paidAt
                ], 'id = :id', ['id' => $bid]);

                $remaining -= $allocAmount;
            }

            log_activity(Auth::id(), 'record_payment', 'billing', "Recorded payment {$receiptNo} of {$netAmount} for {$consumer['full_name']}", [
                'payment_id' => $paymentId,
                'consumer_id' => $consumerId,
                'amount' => $netAmount,
                'method' => $paymentMethod
            ]);

            db()->commit();

            set_flash('success', "Payment recorded successfully. Receipt: {$receiptNo}");
            redirect(ADMIN_URL . "billing/receipt.php?payment_id={$paymentId}");
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$methods = ['cash' => 'Cash', 'bank' => 'Bank', 'esewa' => 'eSewa', 'khalti' => 'Khalti', 'fonepay' => 'FonePay', 'qr' => 'QR Code', 'cheque' => 'Cheque', 'online' => 'Online'];

ob_start(); ?>
<script>
$(function() {
    let selectedConsumerId = <?= json_encode($selectedConsumerId ?: null) ?>;

    $('#consumerSearch').autocomplete({
        source: function(req, res) {
            $.getJSON('<?= $apiUrl ?>get-consumer-bills.php', { q: req.term, action: 'search' }, function(data) {
                res(data);
            });
        },
        minLength: 2,
        select: function(e, ui) {
            selectedConsumerId = ui.item.id;
            $('#consumerSearch').val(ui.item.label);
            loadOutstandingBills(ui.item.id);
            return false;
        }
    }).data('ui-autocomplete')._renderItem = function(ul, item) {
        return $('<li>').append('<div>' + item.label + ' <small class="text-muted">(' + item.consumer_no + ')</small></div>').appendTo(ul);
    };

    function loadOutstandingBills(consumerId) {
        $.getJSON('<?= $apiUrl ?>get-consumer-bills.php', { consumer_id: consumerId }, function(res) {
            var html = '';
            var totalDue = 0;

            if (res.success && res.data && res.data.length > 0) {
                $.each(res.data, function(i, bill) {
                    totalDue += parseFloat(bill.due_amount);
                    html += '<tr>';
                    html += '<td><input type="checkbox" class="bill-select" name="bill_ids[]" value="' + bill.id + '" data-due="' + bill.due_amount + '"></td>';
                    html += '<td>' + bill.bill_no + '</td>';
                    html += '<td>' + bill.billing_period_start + ' to ' + bill.billing_period_end + '</td>';
                    html += '<td class="text-end">' + bill.total_amount + '</td>';
                    html += '<td class="text-end">' + bill.paid_amount + '</td>';
                    html += '<td class="text-end fw-semibold text-danger">' + bill.due_amount + '</td>';
                    html += '<td>' + bill.status + '</td>';
                    html += '</tr>';
                });

                $('#outstandingTotal').text(totalDue.toFixed(2));
                $('#outstandingCount').text(res.data.length);
            } else {
                html = '<tr><td colspan="7" class="text-center text-muted py-4">No outstanding bills found for this consumer</td></tr>';
                $('#outstandingTotal').text('0.00');
                $('#outstandingCount').text('0');
            }

            $('#billsTableBody').html(html);
            $('#consumerId').val(consumerId);
            $('#paymentSection').show();
            updateTotalAmount();
        });
    }

    $(document).on('change', '.bill-select', function() {
        updateTotalAmount();
    });

    function updateTotalAmount() {
        var total = 0;
        $('.bill-select:checked').each(function() {
            total += parseFloat($(this).data('due'));
        });
        $('#totalDueDisplay').val(total.toFixed(2));
        $('#amount').val(total.toFixed(2));
        calcNet();
    }

    $('#amount').on('input', calcNet);
    $('#discount').on('input', calcNet);
    $('#penalty_waived').on('input', calcNet);

    function calcNet() {
        var amount = parseFloat($('#amount').val()) || 0;
        var discount = parseFloat($('#discount').val()) || 0;
        var waived = parseFloat($('#penalty_waived').val()) || 0;
        var net = amount - discount - waived;
        if (net < 0) net = 0;
        $('#netAmount').val(net.toFixed(2));
    }

    $('#payment_method').change(function() {
        var val = $(this).val();
        if (val === 'bank' || val === 'cheque') {
            $('#bankFields').show();
        } else {
            $('#bankFields').hide();
        }
        if (['esewa', 'khalti', 'fonepay', 'online'].includes(val)) {
            $('#onlineFields').show();
        } else {
            $('#onlineFields').hide();
        }
    });

    if (selectedConsumerId) {
        $.get('<?= $apiUrl ?>get-consumer-bills.php', { consumer_id: selectedConsumerId, action: 'info' }, function(res) {
            if (res.success) {
                $('#consumerSearch').val(res.data.full_name + ' (' + res.data.consumer_no + ')');
                selectedConsumerId = res.data.id;
                $('#consumerId').val(selectedConsumerId);
                loadOutstandingBills(selectedConsumerId);
            }
        });
    }
});
</script>
<?php
$extraJs = ob_get_clean();

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-hand-holding-usd me-2 text-success"></i>Record Payment
        </h4>
        <a href="<?= ADMIN_URL ?>billing/payments.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Payments
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-search me-2"></i>Search Consumer</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Consumer <span class="text-danger">*</span></label>
                    <input type="text" id="consumerSearch" class="form-control" placeholder="Type name or consumer no..."
                           value="<?= $selectedConsumerId ? '' : '' ?>">
                    <div class="form-text">Type at least 2 characters to search</div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-calculator me-2"></i>Payment Details</h5>
            </div>
            <div class="card-body">
                <form method="post" id="paymentForm" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="consumer_id" id="consumerId">
                    <input type="hidden" name="net_amount" id="netAmount">

                    <div class="mb-3">
                        <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="">Select Method</option>
                            <?php foreach ($methods as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="bankFields" style="display:none">
                        <div class="mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cheque No</label>
                            <input type="text" name="cheque_no" class="form-control">
                        </div>
                    </div>

                    <div id="onlineFields" style="display:none">
                        <div class="mb-3">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" name="transaction_id" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Total Due Display</label>
                        <input type="text" id="totalDueDisplay" class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="amount" class="form-control" required min="0.01">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Discount</label>
                        <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0" min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Penalty Waived</label>
                        <input type="number" step="0.01" name="penalty_waived" id="penalty_waived" class="form-control" value="0" min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Net Amount</label>
                        <input type="text" id="netAmountDisplay" class="form-control fw-bold fs-5 text-success" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-check-circle me-1"></i>Record Payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card" id="paymentSection" style="display:none">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Outstanding Bills</h5>
                <div>
                    <span class="small text-muted">Total Due: </span>
                    <span class="fw-bold text-danger" id="outstandingTotal">0.00</span>
                    <span class="small text-muted ms-2">Bills: </span>
                    <span class="badge bg-info" id="outstandingCount">0</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Bill No</th>
                                <th>Period</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="billsTableBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Search and select a consumer to view bills</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>Select the bills you want to pay. Payment will be allocated in order of selection.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function updateNetDisplay() {
        var net = parseFloat($('#netAmount').val()) || 0;
        $('#netAmountDisplay').val(net.toFixed(2));
    }

    $('#amount, #discount, #penalty_waived').on('input', function() {
        var amount = parseFloat($('#amount').val()) || 0;
        var discount = parseFloat($('#discount').val()) || 0;
        var waived = parseFloat($('#penalty_waived').val()) || 0;
        var net = amount - discount - waived;
        if (net < 0) net = 0;
        $('#netAmount').val(net.toFixed(2));
        $('#netAmountDisplay').val(net.toFixed(2));
    });

    updateNetDisplay();
});
</script>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

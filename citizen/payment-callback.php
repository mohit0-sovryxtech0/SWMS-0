<?php
require_once __DIR__ . '/../includes/config.php';

if (!citizenLoggedIn()) {
    header('Location: ' . CITIZEN_URL . 'login.php');
    exit;
}

$consumerId = citizenId();
$gateway = get('gateway', '');
$status = get('status', '');
$billId = intval(get('bill_id', 0));
$error = '';
$success = '';

try {
    // Handle payment callback from eSewa
    if ($gateway === 'esewa' && $status) {
        $pending = $_SESSION['payment_pending'] ?? [];
        if (empty($pending) || $pending['gateway'] !== 'esewa') {
            throw new Exception('Payment session expired');
        }
        if ($status === 'success' || get('refId')) {
            require_once INCLUDES_PATH . 'BillingEngine.php';
            $result = BillingEngine::recordPayment([
                'bill_ids' => $pending['bill_ids'],
                'consumer_id' => $pending['consumer_id'],
                'amount' => $pending['amount'],
                'payment_method' => 'esewa',
                'payment_mode' => 'online',
                'transaction_id' => $pending['transaction_id'],
                'remarks' => 'Online payment via eSewa'
            ]);
            unset($_SESSION['payment_pending']);
            alert_success("Payment successful. Receipt: {$result['receipt_no']}");
            redirect(CITIZEN_URL . 'receipt.php?payment_id=' . $result['payment_id']);
        } else {
            unset($_SESSION['payment_pending']);
            alert_error('eSewa payment was cancelled or failed. Please try again.');
            redirect(CITIZEN_URL . 'bills.php');
        }
    }

    // Handle Khalti callback (AJAX POST from KhaltiCheckout)
    if ($gateway === 'khalti' && isPost() && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        require_once INCLUDES_PATH . 'BillingEngine.php';
        $input = json_decode(file_get_contents('php://input'), true);
        $pending = $_SESSION['payment_pending'] ?? [];

        if (empty($input['payload']['token'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment payload']);
            exit;
        }

        try {
            $result = BillingEngine::recordPayment([
                'bill_ids' => $input['bill_ids'] ?? $pending['bill_ids'],
                'consumer_id' => $pending['consumer_id'] ?? $consumerId,
                'amount' => ($input['payload']['amount'] ?? 0) / 100,
                'payment_method' => 'khalti',
                'payment_mode' => 'online',
                'transaction_id' => $input['transaction_id'] ?? $pending['transaction_id'],
                'remarks' => 'Online payment via Khalti'
            ]);
            unset($_SESSION['payment_pending']);
            echo json_encode([
                'success' => true,
                'receipt_no' => $result['receipt_no'],
                'redirect' => CITIZEN_URL . 'receipt.php?payment_id=' . $result['payment_id']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Handle Fonepay callback
    if ($gateway === 'fonepay' && $status) {
        $pending = $_SESSION['payment_pending'] ?? [];
        if (empty($pending) || $pending['gateway'] !== 'fonepay') {
            throw new Exception('Payment session expired');
        }
        if ($status === 'success') {
            require_once INCLUDES_PATH . 'BillingEngine.php';
            $result = BillingEngine::recordPayment([
                'bill_ids' => $pending['bill_ids'],
                'consumer_id' => $pending['consumer_id'],
                'amount' => $pending['amount'],
                'payment_method' => 'fonepay',
                'payment_mode' => 'online',
                'transaction_id' => $pending['transaction_id'],
                'remarks' => 'Online payment via Fonepay'
            ]);
            unset($_SESSION['payment_pending']);
            alert_success("Payment successful. Receipt: {$result['receipt_no']}");
            redirect(CITIZEN_URL . 'receipt.php?payment_id=' . $result['payment_id']);
        } else {
            unset($_SESSION['payment_pending']);
            alert_error('FonePay payment was cancelled or failed.');
            redirect(CITIZEN_URL . 'bills.php');
        }
    }

    // Handle pending payment initialization (form POST from payment page)
    if (isPost()) {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Invalid request');

        $paymentMethod = post('payment_method', '');
        $billIds = post('bill_ids', []);
        $amount = floatval(post('amount', 0));

        if (empty($billIds)) throw new Exception('Please select bills to pay');
        if ($amount <= 0) throw new Exception('Invalid payment amount');

        require_once INCLUDES_PATH . 'BillingEngine.php';
        $transactionId = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $_SESSION['payment_pending'] = [
            'gateway' => $paymentMethod,
            'transaction_id' => $transactionId,
            'bill_ids' => $billIds,
            'amount' => $amount,
            'consumer_id' => $consumerId
        ];

        if ($paymentMethod === 'esewa') {
            $gatewayData = BillingEngine::initiateEsewaPayment($amount, $transactionId, 'BILL-' . implode(',', $billIds), []);
            ?>
            <!DOCTYPE html>
            <html><head><title>Redirecting to eSewa...</title></head><body>
            <form id="esewaForm" method="POST" action="<?= escape($gatewayData['api_url']) ?>">
                <input type="hidden" name="amt" value="<?= $amount ?>">
                <input type="hidden" name="pdc" value="0">
                <input type="hidden" name="psc" value="0">
                <input type="hidden" name="txAmt" value="0">
                <input type="hidden" name="tAmt" value="<?= $amount ?>">
                <input type="hidden" name="pid" value="<?= escape($transactionId) ?>">
                <input type="hidden" name="scd" value="<?= escape($gatewayData['merchant_id']) ?>">
                <input type="hidden" name="su" value="<?= escape($gatewayData['success_url']) ?>">
                <input type="hidden" name="fu" value="<?= escape($gatewayData['failure_url']) ?>">
            </form>
            <script>document.getElementById('esewaForm').submit();</script>
            </body></html>
            <?php
            exit;
        } elseif ($paymentMethod === 'khalti') {
            $gatewayData = BillingEngine::initiateKhaltiPayment($amount, $transactionId, []);
            $billIdsJson = json_encode(array_map('intval', $billIds));
            ?>
            <!DOCTYPE html>
            <html><head><title>Processing Khalti Payment...</title>
            <script src="https://khalti.com/static/khalti-checkout.js"></script>
            </head><body>
            <script>
            var config = {
                publicKey: '<?= escape($gatewayData['merchant_id']) ?>',
                productIdentity: '<?= escape($transactionId) ?>',
                productName: 'Water Bill Payment',
                amount: <?= $gatewayData['amount'] ?>,
                eventHandler: {
                    onSuccess(payload) {
                        fetch('<?= CITIZEN_URL ?>payment-callback.php?gateway=khalti', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                payload: payload,
                                transaction_id: '<?= $transactionId ?>',
                                bill_ids: <?= $billIdsJson ?>
                            })
                        }).then(r => r.json()).then(d => {
                            if (d.success) window.location.href = d.redirect || '<?= CITIZEN_URL ?>bills.php?payment=success';
                            else alert(d.message || 'Payment verification failed');
                        });
                    },
                    onError(err) { alert('Payment failed: ' + err); }
                }
            };
            var checkout = new KhaltiCheckout(config);
            checkout.show({amount: <?= $gatewayData['amount'] ?>});
            </script>
            </body></html>
            <?php
            exit;
        } elseif ($paymentMethod === 'fonepay') {
            $gatewayData = BillingEngine::initiateFonepayPayment($amount, $transactionId, 'BILL-' . implode(',', $billIds), []);
            if (!empty($gatewayData['api_url'])) {
                ?>
                <form id="fonepayForm" method="POST" action="<?= escape($gatewayData['api_url']) ?>">
                    <input type="hidden" name="MERCHANT_ID" value="<?= escape($gatewayData['merchant_id']) ?>">
                    <input type="hidden" name="AMOUNT" value="<?= $amount ?>">
                    <input type="hidden" name="TRANSACTION_ID" value="<?= escape($transactionId) ?>">
                    <input type="hidden" name="DESCRIPTION" value="Water Bill Payment">
                    <input type="hidden" name="RETURN_URL" value="<?= escape($gatewayData['return_url']) ?>">
                </form>
                <script>document.getElementById('fonepayForm').submit();</script>
                <?php
            } else {
                throw new Exception('FonePay gateway not fully configured. Please set API URL in admin settings.');
            }
            exit;
        } elseif ($paymentMethod === 'qr') {
            // Generate QR payment page
            $gatewayConfig = BillingEngine::getPaymentGateway('qr');
            ?>
            <!DOCTYPE html>
            <html><head><title>QR Payment</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            </head><body>
            <div class="container py-5 text-center">
                <h4><i class="fas fa-qrcode me-2"></i>QR Payment</h4>
                <div class="card mx-auto mt-4" style="max-width:400px;">
                    <div class="card-body p-4">
                        <p class="text-muted">Scan the QR code with your payment app to pay:</p>
                        <div id="qrContainer" class="my-4">
                            <div class="display-1 text-muted"><i class="fas fa-qrcode"></i></div>
                        </div>
                        <p class="fw-bold">Amount: NRs. <?= number_format($amount, 2) ?></p>
                        <p class="text-muted small">Transaction: <?= escape($transactionId) ?></p>
                        <hr>
                        <p class="text-muted small">After scanning and paying, click the button below:</p>
                        <button class="btn btn-success btn-lg" onclick="confirmQR()">
                            <i class="fas fa-check me-1"></i>I Have Paid
                        </button>
                    </div>
                </div>
            </div>
            <script>
            function confirmQR() {
                if (!confirm('Have you completed the QR payment?')) return;
                fetch('<?= CITIZEN_URL ?>payment-callback.php?gateway=qr&status=success', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        transaction_id: '<?= $transactionId ?>',
                        bill_ids: <?= json_encode(array_map('intval', $billIds)) ?>,
                        amount: <?= $amount ?>
                    })
                }).then(r => r.json()).then(d => {
                    if (d.success) window.location.href = d.redirect || '<?= CITIZEN_URL ?>bills.php?payment=success';
                    else alert(d.message || 'Payment verification failed');
                });
            }
            </script>
            </body></html>
            <?php
            exit;
        } else {
            throw new Exception('Invalid payment method selected');
        }
    }

    // Handle QR callback confirmation (AJAX)
    if ($gateway === 'qr' && $status === 'success' && isPost()) {
        require_once INCLUDES_PATH . 'BillingEngine.php';
        $input = json_decode(file_get_contents('php://input'), true);
        $pending = $_SESSION['payment_pending'] ?? [];

        try {
            $result = BillingEngine::recordPayment([
                'bill_ids' => $input['bill_ids'] ?? $pending['bill_ids'],
                'consumer_id' => $pending['consumer_id'] ?? $consumerId,
                'amount' => $input['amount'] ?? $pending['amount'],
                'payment_method' => 'qr',
                'payment_mode' => 'online',
                'transaction_id' => $input['transaction_id'] ?? $pending['transaction_id'],
                'remarks' => 'Payment via QR scan'
            ]);
            unset($_SESSION['payment_pending']);
            echo json_encode([
                'success' => true,
                'receipt_no' => $result['receipt_no'],
                'redirect' => CITIZEN_URL . 'receipt.php?payment_id=' . $result['payment_id']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Handle "Pay Now" from bill detail page
    if ($billId > 0) {
        $bill = db()->fetchOne(
            "SELECT id, bill_no, total_amount, due_amount, status FROM bills WHERE id = ? AND consumer_id = ? AND deleted_at IS NULL",
            [$billId, $consumerId]
        );
        if (!$bill || $bill['status'] === 'paid' || $bill['status'] === 'cancelled') {
            alert_error('Bill not found or already paid');
            redirect(CITIZEN_URL . 'bills.php');
        }
        $_SESSION['pre_selected_bills'] = [$bill['id']];
        $_SESSION['pre_selected_amount'] = $bill['due_amount'];
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Load pending bills for payment form
$preSelectedBills = $_SESSION['pre_selected_bills'] ?? [];
$preSelectedAmount = $_SESSION['pre_selected_amount'] ?? 0;
unset($_SESSION['pre_selected_bills'], $_SESSION['pre_selected_amount']);

$pendingBills = db()->fetchAll(
    "SELECT id, bill_no, billing_period_start, billing_period_end, total_amount, paid_amount, due_amount, status
     FROM bills WHERE consumer_id = ? AND status IN ('pending', 'partial', 'overdue') AND deleted_at IS NULL AND due_amount > 0
     ORDER BY due_date ASC",
    [$consumerId]
);

$pageTitle = 'Pay Bill';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => CITIZEN_URL . 'dashboard.php'],
    ['label' => 'Payment']
];
include_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pay Your Water Bill</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= escape($error) ?></div>
                    <?php endif; ?>
                    <?= display_alert() ?>

                    <?php if (empty($pendingBills)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h5>No Pending Bills</h5>
                        <p class="text-muted">You have no outstanding bills at this time.</p>
                        <a href="<?= CITIZEN_URL ?>bills.php" class="btn btn-primary">View Bills</a>
                    </div>
                    <?php else: ?>
                    <form method="post" id="paymentForm">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Payment Method</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-success w-100 py-3 payment-method" data-method="esewa">
                                        <img src="https://esewa.com.np/common/images/esewa_logo.png" height="28" alt="eSewa" style="max-width:100%;"><br>
                                        <small>eSewa</small>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-primary w-100 py-3 payment-method" data-method="khalti" style="border-color:#5a2d82;color:#5a2d82;">
                                        <img src="https://khalti.com/static/images/logo.png" height="28" alt="Khalti" style="max-width:100%;"><br>
                                        <small>Khalti</small>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-info w-100 py-3 payment-method" data-method="fonepay">
                                        <i class="fas fa-university fa-2x d-block mb-1"></i>
                                        <small>FonePay</small>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-dark w-100 py-3 payment-method" data-method="qr">
                                        <i class="fas fa-qrcode fa-2x d-block mb-1"></i>
                                        <small>QR Payment</small>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Bills to Pay</label>
                            <div class="border rounded p-3" style="max-height:250px;overflow-y:auto;">
                                <?php $totalDue = 0; ?>
                                <?php foreach ($pendingBills as $b):
                                    $due = floatval($b['due_amount']);
                                    $totalDue += $due;
                                    $isPreSelected = in_array($b['id'], $preSelectedBills);
                                ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input bill-check" type="checkbox"
                                           name="bill_ids[]" value="<?= $b['id'] ?>"
                                           data-amount="<?= $due ?>"
                                           id="bill_<?= $b['id'] ?>"
                                           <?= $isPreSelected ? 'checked' : '' ?>>
                                    <label class="form-check-label d-flex justify-content-between" for="bill_<?= $b['id'] ?>">
                                        <span><strong><?= escape($b['bill_no'])</strong><br>
                                            <small class="text-muted"><?= format_date($b['billing_period_start']) ?> - <?= format_date($b['billing_period_end']) ?></small>
                                        </span>
                                        <span class="fw-bold text-danger">NRs. <?= number_format($due, 2) ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Total Due Amount:</span>
                            <span class="fs-4 fw-bold text-primary" id="totalDisplay">NRs. <?= number_format($preSelectedAmount ?: $totalDue, 2) ?></span>
                        </div>

                        <input type="hidden" name="amount" id="payAmount" value="<?= $preSelectedAmount ?: $totalDue ?>">
                        <button type="submit" id="payBtn" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-lock me-2"></i>Proceed to Payment
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.payment-method { transition: all 0.2s; cursor: pointer; }
.payment-method:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.payment-method.selected { transform: scale(1.03); box-shadow: 0 4px 16px rgba(0,0,0,0.15); border-width: 2px; }
</style>

<?php ob_start(); ?>
<script>
$(function() {
    let selectedMethod = '';

    $('.payment-method').on('click', function() {
        $('.payment-method').removeClass('selected');
        $(this).addClass('selected');
        selectedMethod = $(this).data('method');
        updatePayBtn();
    });

    $(document).on('change', '.bill-check', function() {
        updateTotal();
        updatePayBtn();
    });

    function updateTotal() {
        var total = 0;
        $('.bill-check:checked').each(function() {
            total += parseFloat($(this).data('amount'));
        });
        $('#totalDisplay').text('NRs. ' + total.toFixed(2));
        $('#payAmount').val(total.toFixed(2));
    }

    function updatePayBtn() {
        var hasBills = $('.bill-check:checked').length > 0;
        var hasMethod = selectedMethod !== '';
        $('#payBtn').prop('disabled', !(hasBills && hasMethod));
        if (hasMethod) {
            var label = selectedMethod.toUpperCase();
            $('#payBtn').html('<i class="fas fa-lock me-2"></i>Pay with ' + label);
        } else {
            $('#payBtn').html('<i class="fas fa-lock me-2"></i>Select Payment Method');
        }
    }

    $('#paymentForm').on('submit', function(e) {
        if ($('.bill-check:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one bill to pay');
            return;
        }
        if (!selectedMethod) {
            e.preventDefault();
            alert('Please select a payment method');
            return;
        }
        $('<input>').attr({type: 'hidden', name: 'payment_method', value: selectedMethod}).appendTo('#paymentForm');
    });

    <?php if (!empty($preSelectedBills)): ?>
    updateTotal();
    updatePayBtn();
    <?php endif; ?>
});
</script>
<?php
$extraJs = ob_get_clean();
include_once __DIR__ . '/includes/footer.php';
?>

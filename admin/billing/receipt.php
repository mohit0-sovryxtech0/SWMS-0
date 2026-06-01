<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();

$paymentId = intval(get('payment_id'));
$billId = intval(get('bill_id'));

if (!$paymentId && !$billId) {
    die('Invalid request');
}

$payment = null;
$bill = null;
$organization = db()->fetchOne("SELECT * FROM organizations LIMIT 1");

if ($paymentId) {
    $payment = db()->fetchOne(
        "SELECT p.*, c.full_name, c.consumer_no, c.mobile, c.address, c.ward_no, c.tole,
                u.name as received_by_name
         FROM payments p
         JOIN consumers c ON p.consumer_id = c.id
         LEFT JOIN users u ON p.received_by = u.id
         WHERE p.id = ?",
        [$paymentId]
    );

    if (!$payment) {
        die('Payment not found');
    }

    $bills = db()->fetchAll(
        "SELECT b.bill_no, b.billing_period_start, b.billing_period_end, b.total_amount, b.paid_amount, b.due_amount,
                bp.amount as allocated_amount
         FROM bill_payments bp
         JOIN bills b ON bp.bill_id = b.id
         WHERE bp.payment_id = ?",
        [$paymentId]
    );
}

if ($billId) {
    $bill = db()->fetchOne(
        "SELECT b.*, c.full_name, c.consumer_no, c.mobile, c.address, c.ward_no, c.tole,
                m.meter_no, fy.label as fiscal_year_label
         FROM bills b
         JOIN consumers c ON b.consumer_id = c.id
         LEFT JOIN meters m ON b.meter_id = m.id
         LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
         WHERE b.id = ?",
        [$billId]
    );

    if (!$bill) {
        die('Bill not found');
    }

    $payments = db()->fetchAll(
        "SELECT p.*, bp.amount as allocated_amount
         FROM payments p
         JOIN bill_payments bp ON p.id = bp.payment_id AND bp.bill_id = ?
         ORDER BY p.payment_date",
        [$billId]
    );
}

$pageTitle = $payment ? 'Receipt - ' . $payment['receipt_no'] : 'Bill - ' . $bill['bill_no'];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .receipt-card { box-shadow: none !important; border: 1px solid #ddd !important; }
            @page { margin: 10mm; }
        }
        body { background: #f5f5f5; font-family: 'Courier New', monospace; font-size: 13px; }
        .receipt-card {
            max-width: 800px; margin: 20px auto; background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px;
        }
        .receipt-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .receipt-header h3 { margin: 0; font-size: 18px; font-weight: bold; }
        .receipt-header p { margin: 2px 0; font-size: 12px; color: #555; }
        .receipt-title { text-align: center; font-size: 16px; font-weight: bold; margin: 15px 0; text-transform: uppercase; letter-spacing: 2px; }
        .table-bordered-custom { width: 100%; border-collapse: collapse; }
        .table-bordered-custom th, .table-bordered-custom td { border: 1px solid #333; padding: 6px 8px; font-size: 12px; }
        .table-bordered-custom th { background: #f0f0f0; font-weight: bold; }
        .amount-in-words { font-style: italic; color: #555; margin-top: 10px; font-size: 12px; }
        .footer-note { text-align: center; margin-top: 20px; font-size: 11px; color: #777; border-top: 1px dashed #ccc; padding-top: 10px; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 30px; }
        .signature-area div { text-align: center; }
        .signature-line { width: 200px; border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="no-print text-center my-3">
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
        <button onclick="window.close()" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Close</button>
    </div>

    <div class="receipt-card">
        <?php if ($payment): ?>
        <!-- Payment Receipt -->
        <div class="receipt-header">
            <?php if ($organization): ?>
            <h3><?= escape($organization['name']) ?></h3>
            <p><?= escape($organization['address'] ?? '') ?></p>
            <p>Phone: <?= escape($organization['phone'] ?? '') ?> | PAN: <?= escape($organization['pan_no'] ?? 'N/A') ?></p>
            <?php else: ?>
            <h3><?= APP_NAME ?></h3>
            <?php endif; ?>
        </div>

        <div class="receipt-title">Payment Receipt</div>

        <div class="d-flex justify-content-between mb-3">
            <div>
                <strong>Receipt No:</strong> <?= escape($payment['receipt_no']) ?><br>
                <strong>Date:</strong> <?= format_date($payment['payment_date']) ?><br>
                <strong>Method:</strong> <?= escape(ucfirst($payment['payment_method'])) ?><br>
                <?php if ($payment['transaction_id']): ?>
                <strong>Transaction ID:</strong> <?= escape($payment['transaction_id']) ?><br>
                <?php endif; ?>
                <?php if ($payment['bank_name']): ?>
                <strong>Bank:</strong> <?= escape($payment['bank_name']) ?>
                <?php if ($payment['cheque_no']): ?> | Cheque: <?= escape($payment['cheque_no']) ?><?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <strong>Consumer:</strong> <?= escape($payment['full_name']) ?><br>
                <strong>Consumer No:</strong> <?= escape($payment['consumer_no']) ?><br>
                <strong>Mobile:</strong> <?= escape($payment['mobile']) ?><br>
                <strong>Ward:</strong> <?= escape($payment['ward_no']) ?>
            </div>
        </div>

        <table class="table-bordered-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill No</th>
                    <th>Period</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; $totalAlloc = 0; ?>
                <?php foreach ($bills as $bp): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= escape($bp['bill_no']) ?></td>
                    <td><?= format_date($bp['billing_period_start']) ?> - <?= format_date($bp['billing_period_end']) ?></td>
                    <td class="text-end"><?= format_currency($bp['allocated_amount']) ?></td>
                </tr>
                <?php $totalAlloc += $bp['allocated_amount']; ?>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Subtotal</td>
                    <td class="text-end fw-bold"><?= format_currency($payment['amount']) ?></td>
                </tr>
                <?php if ($payment['discount'] > 0): ?>
                <tr>
                    <td colspan="3" class="text-end text-success">Discount</td>
                    <td class="text-end text-success">-<?= format_currency($payment['discount']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['penalty_waived'] > 0): ?>
                <tr>
                    <td colspan="3" class="text-end text-success">Penalty Waived</td>
                    <td class="text-end text-success">-<?= format_currency($payment['penalty_waived']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="3" class="text-end fw-bold" style="font-size:14px">Net Amount Paid</td>
                    <td class="text-end fw-bold" style="font-size:14px"><?= format_currency($payment['net_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="amount-in-words">
            Amount in words: <strong><?= ucwords(number_to_words($payment['net_amount'])) ?> Only</strong>
        </div>

        <?php if ($payment['remarks']): ?>
        <div class="mt-2">
            <strong>Remarks:</strong> <?= escape($payment['remarks']) ?>
        </div>
        <?php endif; ?>

        <div class="signature-area">
            <div>
                <div class="signature-line">Received By</div>
                <div><?= escape($payment['received_by_name'] ?? '') ?></div>
            </div>
            <div>
                <div class="signature-line">Consumer Signature</div>
            </div>
            <div>
                <div class="signature-line">Authorized Signature</div>
            </div>
        </div>

        <div class="footer-note">
            This is a computer-generated receipt. No signature required.<br>
            <?= APP_NAME ?> - <?= APP_ORG ?>
        </div>

        <?php elseif ($bill): ?>
        <!-- Bill Print -->
        <div class="receipt-header">
            <?php if ($organization): ?>
            <h3><?= escape($organization['name']) ?></h3>
            <p><?= escape($organization['address'] ?? '') ?></p>
            <p>Phone: <?= escape($organization['phone'] ?? '') ?> | PAN: <?= escape($organization['pan_no'] ?? 'N/A') ?></p>
            <?php else: ?>
            <h3><?= APP_NAME ?></h3>
            <?php endif; ?>
        </div>

        <div class="receipt-title">Water Bill</div>

        <div class="d-flex justify-content-between mb-3">
            <div>
                <strong>Bill No:</strong> <?= escape($bill['bill_no']) ?><br>
                <strong>Date:</strong> <?= format_date($bill['generated_at']) ?><br>
                <strong>Fiscal Year:</strong> <?= escape($bill['fiscal_year_label']) ?><br>
                <strong>Due Date:</strong> <?= format_date($bill['due_date']) ?>
            </div>
            <div class="text-end">
                <strong><?= escape($bill['full_name']) ?></strong><br>
                Consumer No: <?= escape($bill['consumer_no']) ?><br>
                Mobile: <?= escape($bill['mobile']) ?><br>
                Ward: <?= escape($bill['ward_no']) ?>, <?= escape($bill['tole'] ?? '') ?>
            </div>
        </div>

        <table class="table-bordered-custom">
            <tr>
                <td><strong>Meter No:</strong> <?= escape($bill['meter_no'] ?? 'N/A') ?></td>
                <td><strong>Previous Reading:</strong> <?= number_format($bill['previous_reading'], 2) ?></td>
                <td><strong>Current Reading:</strong> <?= number_format($bill['current_reading'], 2) ?></td>
                <td><strong>Consumption:</strong> <?= number_format($bill['consumption'], 2) ?> Units</td>
            </tr>
        </table>

        <table class="table-bordered-custom mt-2">
            <thead>
                <tr><th>Description</th><th class="text-end">Amount (<?= APP_ORG ?>)</th></tr>
            </thead>
            <tbody>
                <tr><td>Base Fee</td><td class="text-end"><?= format_currency($bill['base_fee']) ?></td></tr>
                <tr><td>Consumption Charge (<?= number_format($bill['consumption'], 2) ?> units)</td><td class="text-end"><?= format_currency($bill['consumption_charge']) ?></td></tr>
                <tr><td>Meter Rent</td><td class="text-end"><?= format_currency($bill['meter_rent']) ?></td></tr>
                <tr><td>Sewerage Fee</td><td class="text-end"><?= format_currency($bill['sewerage_fee']) ?></td></tr>
                <?php if ($bill['discount_amount'] > 0): ?>
                <tr><td class="text-success">Discount</td><td class="text-end text-success">-<?= format_currency($bill['discount_amount']) ?></td></tr>
                <?php endif; ?>
                <?php if ($bill['penalty_amount'] > 0): ?>
                <tr><td class="text-danger">Penalty</td><td class="text-end text-danger"><?= format_currency($bill['penalty_amount']) ?></td></tr>
                <?php endif; ?>
                <tr><td>VAT</td><td class="text-end"><?= format_currency($bill['vat_amount']) ?></td></tr>
                <tr style="border-top:2px solid #333">
                    <td class="fw-bold" style="font-size:14px">Total Amount</td>
                    <td class="text-end fw-bold" style="font-size:14px"><?= format_currency($bill['total_amount']) ?></td>
                </tr>
                <?php if ($bill['paid_amount'] > 0): ?>
                <tr><td class="text-success">Paid Amount</td><td class="text-end text-success"><?= format_currency($bill['paid_amount']) ?></td></tr>
                <tr><td class="fw-bold <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>">Due Amount</td>
                    <td class="text-end fw-bold <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($bill['due_amount']) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="amount-in-words">
            Amount in words: <strong><?= ucwords(number_to_words($bill['total_amount'])) ?> Only</strong>
        </div>

        <div class="footer-note">
            <?= get_status_badge($bill['status']) ?><br>
            This is a computer-generated bill. <br>
            <?= APP_NAME ?> - <?= APP_ORG ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    window.onload = function() {
        // Auto print if ?print=1
        var params = new URLSearchParams(window.location.search);
        if (params.get('print') === '1') {
            setTimeout(function() { window.print(); }, 500);
        }
    };
    </script>
</body>
</html>
<?php
// Helper for number to words
function number_to_words($number) {
    $no = floor($number);
    $decimal = round($number - $no, 2) * 100;
    $digits = ['Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
               'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($no == 0) return 'Zero';

    $words = '';
    $places = [
        [10000000, 'Crore'],
        [100000, 'Lakh'],
        [1000, 'Thousand'],
        [100, 'Hundred']
    ];

    foreach ($places as $place) {
        if ($no >= $place[0]) {
            $val = floor($no / $place[0]);
            $words .= number_to_words($val) . ' ' . $place[1] . ' ';
            $no %= $place[0];
        }
    }

    if ($no > 0) {
        if ($no < 20) {
            $words .= $digits[$no] . ' ';
        } else {
            $words .= $tens[floor($no / 10)] . ' ';
            if ($no % 10 > 0) {
                $words .= $digits[$no % 10] . ' ';
            }
        }
    }

    if ($decimal > 0) {
        $words .= 'and ' . number_to_words($decimal) . ' Paisa';
    }

    return trim($words) . ' Rupees';
}
?>

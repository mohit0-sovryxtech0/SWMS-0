<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Revenue Report';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports', 'url' => ADMIN_URL . 'reports/index.php'],
    ['label' => 'Revenue']
];
RBAC::requirePermission('reports.view');

$startDate = post('start_date', date('Y-m-01', strtotime('-12 months')));
$endDate = post('end_date', date('Y-m-d'));
$paymentMethod = post('payment_method');
$reportData = null;

if (isPost() && post('action') === 'generate') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }
        $reportData = BillingEngine::getRevenueReport($startDate, $endDate);
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
}

$extraCss = <<<CSS
<style>
.summary-card { border-radius: 10px; border-left: 4px solid; transition: transform 0.2s; }
.summary-card:hover { transform: translateY(-2px); }
</style>
CSS;

$chartMonths = [];
$chartRevenue = [];
$chartNet = [];
if ($reportData && !empty($reportData['monthly'])) {
    foreach ($reportData['monthly'] as $m) {
        $chartMonths[] = $m['month'];
        $chartRevenue[] = (float) $m['total_amount'];
        $chartNet[] = (float) $m['net_amount'];
    }
}

$extraJs = <<<JS
<script>
$(document).ready(function() {
    $('#revenueTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        language: { searchPlaceholder: 'Search...' }
    });

    <?php if (!empty($chartMonths)): ?>
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartMonths) ?>,
            datasets: [{
                label: 'Gross Revenue',
                data: <?= json_encode($chartRevenue) ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 3
            }, {
                label: 'Net Revenue',
                data: <?= json_encode($chartNet) ?>,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                tooltip: { callbacks: { label: function(ctx) { return ctx.dataset.label + ': ' + 'NRs. ' + ctx.parsed.y.toFixed(2); } } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: function(v) { return 'NRs. ' + v.toLocaleString(); } } }
            }
        }
    });
    <?php endif; ?>
});
</script>
JS;

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Revenue Report</h4>
            <p>Revenue collected over time periods with payment method breakdown</p>
        </div>
        <div>
            <a href="<?= ADMIN_URL ?>reports/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<form method="post" class="card mb-4">
    <div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= escape($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= escape($endDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-select">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank" <?= $paymentMethod === 'bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="esewa" <?= $paymentMethod === 'esewa' ? 'selected' : '' ?>>eSewa</option>
                    <option value="khalti" <?= $paymentMethod === 'khalti' ? 'selected' : '' ?>>Khalti</option>
                    <option value="fonepay" <?= $paymentMethod === 'fonepay' ? 'selected' : '' ?>>FonePay</option>
                    <option value="qr" <?= $paymentMethod === 'qr' ? 'selected' : '' ?>>QR</option>
                    <option value="cheque" <?= $paymentMethod === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                    <option value="online" <?= $paymentMethod === 'online' ? 'selected' : '' ?>>Online</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sync me-1"></i> Generate Report
                </button>
            </div>
        </div>
    </div>
</form>

<?= display_alert() ?>

<?php if ($reportData): $s = $reportData['summary']; ?>
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #4e73df;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Gross Revenue</div>
                <div class="fw-bold fs-4"><?= format_currency($s['gross_revenue']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #1cc88a;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Net Revenue</div>
                <div class="fw-bold fs-4"><?= format_currency($s['net_revenue']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #f6c23e;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Discount</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_discount']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #e74a3b;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Penalty Waived</div>
                <div class="fw-bold fs-4"><?= format_currency($s['penalty_waived']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #36b9cc;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Transactions</div>
                <div class="fw-bold fs-4"><?= number_format($s['total_txn']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Monthly Revenue Trend</h6>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="280"></canvas>
                <?php if (empty($chartMonths)): ?>
                <div class="text-center text-muted py-3"><i class="fas fa-chart-line fa-2x mb-2"></i><p class="mb-0">No revenue data for selected period</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-credit-card me-2 text-success"></i>Payment Method Breakdown</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Method</th><th>Transactions</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['method_wise'] as $mw): ?>
                            <tr>
                                <td><span class="badge bg-info"><?= escape(ucfirst($mw['payment_method'])) ?></span></td>
                                <td><?= number_format($mw['txn_count']) ?></td>
                                <td><?= format_currency($mw['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-table me-2 text-primary"></i>Revenue Details</h6>
        <div class="btn-group btn-group-sm">
            <?php if (RBAC::can('exports.pdf')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=revenue&format=pdf&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&payment_method=<?= urlencode($paymentMethod) ?>" class="btn btn-danger" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.excel')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=revenue&format=xlsx&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&payment_method=<?= urlencode($paymentMethod) ?>" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Excel</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.csv')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=revenue&format=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&payment_method=<?= urlencode($paymentMethod) ?>" class="btn btn-secondary"><i class="fas fa-file-csv me-1"></i>CSV</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="revenueTable" class="table table-hover table-bordered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th>Payment Method</th>
                        <th>Transactions</th>
                        <th>Total Amount</th>
                        <th>Net Amount</th>
                        <th>Discount</th>
                        <th>Penalty Waived</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['monthly'] as $r): ?>
                    <tr>
                        <td><?= escape($r['month']) ?></td>
                        <td><span class="badge bg-info"><?= escape(ucfirst($r['payment_method'])) ?></span></td>
                        <td><?= number_format($r['txn_count']) ?></td>
                        <td class="text-end"><?= format_currency($r['total_amount']) ?></td>
                        <td class="text-end"><?= format_currency($r['net_amount']) ?></td>
                        <td class="text-end"><?= format_currency($r['total_discount']) ?></td>
                        <td class="text-end"><?= format_currency($r['penalty_waived']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

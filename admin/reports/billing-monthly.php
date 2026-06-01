<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Monthly Billing Report';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports', 'url' => ADMIN_URL . 'reports/index.php'],
    ['label' => 'Monthly Billing']
];
RBAC::requirePermission('reports.view');

$year = (int) post('year', date('Y'));
$month = str_pad(post('month', date('m')), 2, '0', STR_PAD_LEFT);
$fiscalYearId = post('fiscal_year_id');
$reportData = null;

if (isPost() && post('action') === 'generate') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }
        $reportData = BillingEngine::getMonthlyReport($year, $month);
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
}

$fiscalYears = db()->fetchAll("SELECT id, label FROM fiscal_years ORDER BY start_date DESC");

$extraCss = <<<CSS
<style>
.summary-card { border-radius: 10px; border-left: 4px solid; transition: transform 0.2s; }
.summary-card:hover { transform: translateY(-2px); }
</style>
CSS;

$chartData = [];
$chartLabels = [];
if ($reportData && !empty($reportData['bills'])) {
    $dailyCounts = [];
    foreach ($reportData['bills'] as $b) {
        $day = format_date($b['billing_period_start'] ?? $b['created_at'] ?? date('Y-m-d'), 'Y-m-d');
        $dailyCounts[$day] = ($dailyCounts[$day] ?? 0) + 1;
    }
    ksort($dailyCounts);
    $chartLabels = array_keys($dailyCounts);
    $chartData = array_values($dailyCounts);
}

$extraJs = <<<JS
<script>
$(document).ready(function() {
    var table = $('#monthlyTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        language: { searchPlaceholder: 'Search bills...' }
    });

    <?php if (!empty($chartData)): ?>
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Bills Generated',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.7)',
                borderColor: '#4e73df',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' bills'; } } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
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
            <h4>Monthly Billing Report</h4>
            <p>View bills generated in a selected month/year period</p>
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
                <label class="form-label">Year</label>
                <select name="year" class="form-select">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <select name="month" class="form-select">
                    <?php foreach (range(1, 12) as $m): $mPadded = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $mPadded ?>" <?= $month == $mPadded ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fiscal Year</label>
                <select name="fiscal_year_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>" <?= $fiscalYearId == $fy['id'] ? 'selected' : '' ?>><?= escape($fy['label']) ?></option>
                    <?php endforeach; ?>
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
                <div class="text-muted small">Total Bills</div>
                <div class="fw-bold fs-4"><?= number_format($s['total_bills']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #1cc88a;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Billed</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_billed']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #36b9cc;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Collected</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_collected']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #f6c23e;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Collection %</div>
                <div class="fw-bold fs-4">
                    <?= $s['total_billed'] > 0 ? round(($s['total_collected'] / $s['total_billed']) * 100, 1) : 0 ?>%
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #e74a3b;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Overdue</div>
                <div class="fw-bold fs-4"><?= number_format($s['overdue_count']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #858796;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Cancelled</div>
                <div class="fw-bold fs-4"><?= number_format($s['cancelled_count']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Daily Bills Generated - <?= date('F Y', strtotime($reportData['start_date'])) ?></h6>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" height="250"></canvas>
                <?php if (empty($chartData)): ?>
                <div class="text-center text-muted py-3"><i class="fas fa-chart-bar fa-2x mb-2"></i><p class="mb-0">No data for chart</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-success"></i>Ward-wise Collection</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Ward</th><th>Bills</th><th>Amount</th><th>Collected</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['ward_wise'] as $w): ?>
                            <tr>
                                <td>Ward <?= escape($w['ward_no']) ?></td>
                                <td><?= number_format($w['total']) ?></td>
                                <td><?= format_currency($w['amount']) ?></td>
                                <td><?= format_currency($w['collected']) ?></td>
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
        <h6 class="mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i>Bill Details</h6>
        <div class="btn-group btn-group-sm">
            <?php if (RBAC::can('exports.pdf')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=billing&format=pdf&start_date=<?= urlencode($reportData['start_date']) ?>&end_date=<?= urlencode($reportData['end_date']) ?>" class="btn btn-danger" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.excel')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=billing&format=xlsx&start_date=<?= urlencode($reportData['start_date']) ?>&end_date=<?= urlencode($reportData['end_date']) ?>" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Excel</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.csv')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=billing&format=csv&start_date=<?= urlencode($reportData['start_date']) ?>&end_date=<?= urlencode($reportData['end_date']) ?>" class="btn btn-secondary"><i class="fas fa-file-csv me-1"></i>CSV</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="monthlyTable" class="table table-hover table-bordered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th>Bill No</th>
                        <th>Consumer</th>
                        <th>Consumer No</th>
                        <th>Period</th>
                        <th>Consumption</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Due Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['bills'] as $b): ?>
                    <tr>
                        <td><a href="<?= ADMIN_URL ?>billing/view.php?id=<?= $b['id'] ?>" class="fw-semibold text-decoration-none"><?= escape($b['bill_no']) ?></a></td>
                        <td><?= escape($b['consumer_name']) ?></td>
                        <td><?= escape($b['consumer_no']) ?></td>
                        <td><?= format_date($b['billing_period_start']) ?> - <?= format_date($b['billing_period_end']) ?></td>
                        <td><?= number_format($b['consumption'] ?? 0) ?></td>
                        <td class="text-end"><?= format_currency($b['total_amount']) ?></td>
                        <td class="text-end"><?= format_currency($b['paid_amount']) ?></td>
                        <td class="text-end"><?= format_currency($b['due_amount']) ?></td>
                        <td><?= get_status_badge($b['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

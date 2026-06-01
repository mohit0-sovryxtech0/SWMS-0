<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Consumer Billing History';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports', 'url' => ADMIN_URL . 'reports/index.php'],
    ['label' => 'Consumer History']
];
RBAC::requirePermission('reports.view');

$consumerId = (int) post('consumer_id', get('consumer_id', 0));
$reportData = null;
$consumer = null;

if (consumerId > 0) {
    try {
        $reportData = BillingEngine::getConsumerHistory($consumerId);
        $consumer = $reportData['consumer'];
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
}

$extraCss = <<<CSS
<style>
.summary-card { border-radius: 10px; border-left: 4px solid; transition: transform 0.2s; }
.summary-card:hover { transform: translateY(-2px); }
.consumer-info-card { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: #fff; border: none; border-radius: 12px; }
.consumer-info-card .text-muted { color: rgba(255,255,255,0.7) !important; }
</style>
CSS;

$chartLabels = [];
$chartConsumption = [];
$chartAmounts = [];
if ($reportData && !empty($reportData['bills'])) {
    $billsSorted = array_reverse($reportData['bills']);
    foreach ($billsSorted as $b) {
        $chartLabels[] = format_date($b['billing_period_start'], 'M Y');
        $chartConsumption[] = (float) ($b['consumption'] ?? 0);
        $chartAmounts[] = (float) $b['total_amount'];
    }
}

$extraJs = <<<JS
<script>
$(document).ready(function() {
    $('#billsHistoryTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        language: { searchPlaceholder: 'Search bills...' }
    });

    $('#paymentsHistoryTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        language: { searchPlaceholder: 'Search payments...' }
    });

    <?php if (!empty($chartLabels)): ?>
    new Chart(document.getElementById('consumptionChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Consumption (Units)',
                data: <?= json_encode($chartConsumption) ?>,
                borderColor: '#36b9cc',
                backgroundColor: 'rgba(54, 185, 204, 0.1)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y1'
            }, {
                label: 'Bill Amount',
                data: <?= json_encode($chartAmounts) ?>,
                borderColor: '#e74a3b',
                backgroundColor: 'rgba(231, 74, 59, 0.1)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y'
            }]
        },
        options: {
            responsive: true,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.dataset.yAxisID === 'y1') return 'Consumption: ' + ctx.parsed.y + ' units';
                            return 'Amount: ' + 'NRs. ' + ctx.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Amount (NRs.)' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Consumption (Units)' } }
            }
        }
    });
    <?php endif; ?>
});

function selectConsumer(id, label) {
    $('#consumerId').val(id);
    $('#consumerSearch').val(label);
    $('#consumerResults').addClass('d-none');
    $('#consumerForm').submit();
}
</script>
JS;

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Consumer Billing History</h4>
            <p>View full billing and payment history for a consumer</p>
        </div>
        <div>
            <a href="<?= ADMIN_URL ?>reports/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<form method="post" id="consumerForm" class="card mb-4">
    <div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="search">
        <input type="hidden" name="consumer_id" id="consumerId" value="<?= $consumerId ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search Consumer</label>
                <div class="position-relative">
                    <input type="text" id="consumerSearch" class="form-control" placeholder="Type consumer name or number..." value="<?= $consumer ? escape($consumer['full_name'] . ' (' . $consumer['consumer_no'] . ')') : '' ?>" autocomplete="off">
                    <div id="consumerResults" class="list-group position-absolute w-100 shadow d-none" style="z-index: 1000; max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Load
                </button>
            </div>
        </div>
    </div>
</form>

<?= display_alert() ?>

<?php if ($consumer && $reportData): $s = $reportData['summary']; ?>
<div class="card consumer-info-card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-1"><?= escape($consumer['full_name'] ?? '') ?></h5>
                <p class="mb-0" style="opacity: 0.8;">
                    <span class="me-3"><i class="fas fa-id-card me-1"></i> <?= escape($consumer['consumer_no'] ?? '') ?></span>
                    <?php if (!empty($consumer['mobile'])): ?>
                    <span class="me-3"><i class="fas fa-phone me-1"></i> <?= escape($consumer['mobile']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-map-marker-alt me-1"></i> Ward <?= escape($consumer['ward_no'] ?? '') ?></span>
                </p>
            </div>
            <div class="col-md-3">
                <small style="opacity: 0.7;">Connection Type</small>
                <div><?= escape(ucfirst($consumer['connection_type'] ?? '-')) ?></div>
            </div>
            <div class="col-md-3">
                <small style="opacity: 0.7;">Category</small>
                <div><?= escape($consumer['category_name'] ?? '-') ?></div>
            </div>
        </div>
    </div>
</div>

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
                <div class="text-muted small">Total Paid</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_paid']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #e74a3b;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Due</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_due']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #1cc88a;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Paid Bills</div>
                <div class="fw-bold fs-4"><?= number_format($s['paid_count']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #e74a3b;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Unpaid Bills</div>
                <div class="fw-bold fs-4"><?= number_format($s['unpaid_count']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-chart-area me-2 text-primary"></i>Consumption & Bill Amount Trend</h6>
    </div>
    <div class="card-body">
        <canvas id="consumptionChart" height="280"></canvas>
        <?php if (empty($chartLabels)): ?>
        <div class="text-center text-muted py-3"><i class="fas fa-chart-area fa-2x mb-2"></i><p class="mb-0">No billing data available for chart</p></div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i>Bills History</h6>
        <?php if (RBAC::can('exports.csv')): ?>
        <a href="<?= ADMIN_URL ?>reports/export.php?type=billing&format=csv&start_date=<?= urlencode(date('Y-m-d', strtotime('-5 years'))) ?>&end_date=<?= urlencode(date('Y-m-d')) ?>" class="btn btn-sm btn-secondary"><i class="fas fa-download me-1"></i>Export</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="billsHistoryTable" class="table table-hover table-bordered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th>Bill No</th>
                        <th>Period</th>
                        <th>Due Date</th>
                        <th>Consumption</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Fiscal Year</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['bills'] as $b): ?>
                    <tr>
                        <td><a href="<?= ADMIN_URL ?>billing/view.php?id=<?= $b['id'] ?>" class="fw-semibold text-decoration-none"><?= escape($b['bill_no']) ?></a></td>
                        <td><?= format_date($b['billing_period_start']) ?> - <?= format_date($b['billing_period_end']) ?></td>
                        <td><?= format_date($b['due_date']) ?></td>
                        <td><?= number_format($b['consumption'] ?? 0) ?></td>
                        <td class="text-end"><?= format_currency($b['total_amount']) ?></td>
                        <td class="text-end"><?= format_currency($b['paid_amount']) ?></td>
                        <td class="text-end"><?= format_currency($b['due_amount']) ?></td>
                        <td><?= get_status_badge($b['status']) ?></td>
                        <td><?= escape($b['fiscal_year_label'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Payment History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="paymentsHistoryTable" class="table table-hover table-bordered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Discount</th>
                        <th>Penalty Waived</th>
                        <th>Net Amount</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['payments'] as $p): ?>
                    <tr>
                        <td><strong><?= escape($p['receipt_no']) ?></strong></td>
                        <td><?= format_date($p['payment_date']) ?></td>
                        <td><span class="badge bg-info"><?= escape(ucfirst($p['payment_method'])) ?></span></td>
                        <td class="text-end"><?= format_currency($p['amount']) ?></td>
                        <td class="text-end"><?= format_currency($p['discount'] ?? 0) ?></td>
                        <td class="text-end"><?= format_currency($p['penalty_waived'] ?? 0) ?></td>
                        <td class="text-end fw-semibold"><?= format_currency($p['net_amount']) ?></td>
                        <td><small class="text-muted"><?= escape($p['transaction_id'] ?: $p['reference_no'] ?: '-') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($reportData['readings'])): ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-tachometer-alt me-2 text-secondary"></i>Meter Readings History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Meter No</th>
                        <th>Reading Date</th>
                        <th>Previous Reading</th>
                        <th>Current Reading</th>
                        <th>Consumption</th>
                        <th>Source</th>
                        <th>Verified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['readings'] as $r): ?>
                    <tr>
                        <td><?= escape($r['meter_no']) ?></td>
                        <td><?= format_date($r['reading_date']) ?></td>
                        <td class="text-end"><?= number_format($r['previous_reading'] ?? 0) ?></td>
                        <td class="text-end"><?= number_format($r['current_reading'] ?? 0) ?></td>
                        <td class="text-end"><?= number_format($r['consumption'] ?? 0) ?></td>
                        <td><span class="badge bg-<?= $r['reading_source'] === 'manual' ? 'warning' : 'info' ?>"><?= escape(ucfirst($r['reading_source'] ?? 'manual')) ?></span></td>
                        <td><?= $r['is_verified'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-muted"></i>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
$(document).ready(function() {
    var searchTimeout;
    $('#consumerSearch').on('input', function() {
        var q = $(this).val();
        clearTimeout(searchTimeout);
        if (q.length < 2) {
            $('#consumerResults').addClass('d-none');
            return;
        }
        searchTimeout = setTimeout(function() {
            $.getJSON('<?= API_URL ?>search-consumers.php?q=' + encodeURIComponent(q), function(data) {
                if (data.length === 0) {
                    $('#consumerResults').html('<div class="list-group-item text-muted small">No consumers found</div>').removeClass('d-none');
                    return;
                }
                var html = '';
                $.each(data, function(i, c) {
                    html += '<div class="list-group-item list-group-item-action" style="cursor:pointer" onclick="selectConsumer(' + c.id + ', \'' + c.label.replace(/'/g, "\\'") + '\')">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div><strong>' + escapeHtml(c.label) + '</strong><br><small class="text-muted">' + escapeHtml(c.consumer_no) + '</small></div>';
                    html += '<i class="fas fa-chevron-right text-muted"></i>';
                    html += '</div></div>';
                });
                $('#consumerResults').html(html).removeClass('d-none');
            });
        }, 300);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#consumerSearch, #consumerResults').length) {
            $('#consumerResults').addClass('d-none');
        }
    });

    function escapeHtml(text) {
        return $('<span>').text(text).html();
    }
});
</script>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

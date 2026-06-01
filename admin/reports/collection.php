<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Collection Report';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports', 'url' => ADMIN_URL . 'reports/index.php'],
    ['label' => 'Collection']
];
RBAC::requirePermission('reports.view');

$fiscalYearId = post('fiscal_year_id');
$reportData = null;

if (isPost() && post('action') === 'generate') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }
        $reportData = BillingEngine::getCollectionReport($fiscalYearId);
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

$chartMonths = [];
$chartBilled = [];
$chartCollected = [];
if ($reportData && !empty($reportData['monthly'])) {
    // Reverse for chronological order in chart
    $months = array_reverse($reportData['monthly']);
    foreach ($months as $m) {
        $chartMonths[] = $m['month'];
        $chartBilled[] = (float) $m['total_billed'];
        $chartCollected[] = (float) $m['total_collected'];
    }
}

$extraJs = <<<JS
<script>
$(document).ready(function() {
    $('#collectionTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        language: { searchPlaceholder: 'Search...' }
    });

    <?php if (!empty($chartMonths)): ?>
    new Chart(document.getElementById('collectionChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartMonths) ?>,
            datasets: [{
                label: 'Billed',
                data: <?= json_encode($chartBilled) ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.7)',
                borderColor: '#4e73df',
                borderWidth: 1,
                borderRadius: 3
            }, {
                label: 'Collected',
                data: <?= json_encode($chartCollected) ?>,
                backgroundColor: 'rgba(28, 200, 138, 0.7)',
                borderColor: '#1cc88a',
                borderWidth: 1,
                borderRadius: 3
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
            <h4>Collection Report</h4>
            <p>Collection efficiency and outstanding analysis over time</p>
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
            <div class="col-md-4">
                <label class="form-label">Fiscal Year</label>
                <select name="fiscal_year_id" class="form-select">
                    <option value="">All Fiscal Years</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>" <?= $fiscalYearId == $fy['id'] ? 'selected' : '' ?>><?= escape($fy['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
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
        <div class="card summary-card shadow-sm" style="border-left-color: #e74a3b;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Outstanding</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_outstanding']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card summary-card shadow-sm" style="border-left-color: #f6c23e;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Collection %</div>
                <div class="fw-bold fs-4"><?= number_format($s['collection_pct'], 1) ?>%</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Billed vs Collected by Month</h6>
            </div>
            <div class="card-body">
                <canvas id="collectionChart" height="280"></canvas>
                <?php if (empty($chartMonths)): ?>
                <div class="text-center text-muted py-3"><i class="fas fa-chart-bar fa-2x mb-2"></i><p class="mb-0">No collection data available</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-percentage me-2 text-warning"></i>Collection Efficiency</h6>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <?php
                $pct = $s['total_billed'] > 0 ? round(($s['total_collected'] / $s['total_billed']) * 100, 1) : 0;
                $color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                ?>
                <div class="display-3 fw-bold text-<?= $color ?>"><?= number_format($pct, 1) ?>%</div>
                <p class="text-muted mb-0">Overall Collection Rate</p>
                <div class="progress w-100 mt-3" style="height: 10px;">
                    <div class="progress-bar bg-<?= $color ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                </div>
                <div class="row w-100 mt-3 text-center g-2">
                    <div class="col-6">
                        <small class="text-muted d-block">Billed</small>
                        <strong><?= format_currency($s['total_billed']) ?></strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Collected</small>
                        <strong><?= format_currency($s['total_collected']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-table me-2 text-primary"></i>Monthly Collection Breakdown</h6>
        <div class="btn-group btn-group-sm">
            <?php if (RBAC::can('exports.pdf')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=collection&format=pdf&fiscal_year=<?= urlencode($fiscalYearId) ?>" class="btn btn-danger" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.excel')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=collection&format=xlsx&fiscal_year=<?= urlencode($fiscalYearId) ?>" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Excel</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.csv')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=collection&format=csv&fiscal_year=<?= urlencode($fiscalYearId) ?>" class="btn btn-secondary"><i class="fas fa-file-csv me-1"></i>CSV</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="collectionTable" class="table table-hover table-bordered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th>Total Bills</th>
                        <th>Total Billed</th>
                        <th>Total Collected</th>
                        <th>Outstanding</th>
                        <th>Collection %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['monthly'] as $r): ?>
                    <?php $cpct = $r['total_billed'] > 0 ? round(($r['total_collected'] / $r['total_billed']) * 100, 1) : 0; ?>
                    <tr>
                        <td><?= escape($r['month']) ?></td>
                        <td><?= number_format($r['total_bills']) ?></td>
                        <td class="text-end"><?= format_currency($r['total_billed']) ?></td>
                        <td class="text-end"><?= format_currency($r['total_collected']) ?></td>
                        <td class="text-end"><?= format_currency($r['total_outstanding']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $cpct >= 80 ? 'success' : ($cpct >= 50 ? 'warning' : 'danger') ?>" style="width: <?= $cpct ?>%"></div>
                                </div>
                                <small class="fw-semibold"><?= number_format($cpct, 1) ?>%</small>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

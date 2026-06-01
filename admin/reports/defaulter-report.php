<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Defaulter Report';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports', 'url' => ADMIN_URL . 'reports/index.php'],
    ['label' => 'Defaulters']
];
RBAC::requirePermission('reports.view');

$minMonths = (int) post('min_overdue_months', 2);
$fiscalYearId = post('fiscal_year_id');
$wardNo = post('ward_no');
$reportData = null;

if (isPost() && post('action') === 'generate') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }
        $reportData = BillingEngine::getDefaulters($minMonths, $fiscalYearId);
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
}

$fiscalYears = db()->fetchAll("SELECT id, label FROM fiscal_years ORDER BY start_date DESC");

$extraCss = <<<CSS
<style>
.summary-card { border-radius: 10px; border-left: 4px solid; transition: transform 0.2s; }
.summary-card:hover { transform: translateY(-2px); }
.defaulter-row td { vertical-align: middle; }
.defaulter-detail { background: #f8f9fa; }
.defaulter-detail table { margin: 0; }
</style>
CSS;

$extraJs = <<<JS
<script>
$(document).ready(function() {
    var table = $('#defaulterTable').DataTable({
        pageLength: 25,
        order: [[4, 'desc']],
        responsive: true,
        language: { searchPlaceholder: 'Search defaulters...' }
    });

    $('#defaulterTable tbody').on('click', 'td.details-control', function() {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
        } else {
            row.child(row.data().detail).show();
            tr.addClass('shown');
        }
    });
});
</script>
JS;

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Defaulter Report</h4>
            <p>Overdue consumers with aging analysis</p>
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
                <label class="form-label">Min Overdue Months</label>
                <select name="min_overdue_months" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $minMonths === $m ? 'selected' : '' ?>><?= $m ?> Month<?= $m > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
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
                <label class="form-label">Ward</label>
                <select name="ward_no" class="form-select">
                    <option value="">All Wards</option>
                    <?php for ($w = 1; $w <= 20; $w++): ?>
                    <option value="<?= $w ?>" <?= $wardNo == $w ? 'selected' : '' ?>>Ward <?= $w ?></option>
                    <?php endfor; ?>
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
    <div class="col-xl-4 col-md-4">
        <div class="card summary-card shadow-sm" style="border-left-color: #e74a3b;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Defaulters</div>
                <div class="fw-bold fs-4"><?= number_format($s['total_defaulters']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4">
        <div class="card summary-card shadow-sm" style="border-left-color: #f6c23e;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Overdue Amount</div>
                <div class="fw-bold fs-4"><?= format_currency($s['total_overdue_amount']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4">
        <div class="card summary-card shadow-sm" style="border-left-color: #4e73df;">
            <div class="card-body text-center py-3">
                <div class="text-muted small">Total Overdue Bills</div>
                <div class="fw-bold fs-4"><?= number_format($s['total_overdue_bills']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Defaulter List (<?= count($reportData['consumers']) ?> consumers)</h6>
        <div class="btn-group btn-group-sm">
            <?php if (RBAC::can('exports.pdf')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=defaulter&format=pdf&min_overdue_months=<?= $minMonths ?>&fiscal_year=<?= urlencode($fiscalYearId) ?>" class="btn btn-danger" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.excel')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=defaulter&format=xlsx&min_overdue_months=<?= $minMonths ?>&fiscal_year=<?= urlencode($fiscalYearId) ?>" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Excel</a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.csv')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=defaulter&format=csv&min_overdue_months=<?= $minMonths ?>&fiscal_year=<?= urlencode($fiscalYearId) ?>" class="btn btn-secondary"><i class="fas fa-file-csv me-1"></i>CSV</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="defaulterTable" class="table table-hover table-bordered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th class="no-sort" style="width:30px"></th>
                        <th>Consumer No</th>
                        <th>Consumer Name</th>
                        <th>Mobile</th>
                        <th>Ward</th>
                        <th>Total Due</th>
                        <th>Bills Count</th>
                        <th>Max Months Overdue</th>
                        <th>Oldest Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['consumers'] as $c): ?>
                    <?php
                    $detailHtml = '<div class="p-2"><table class="table table-sm table-bordered mb-0">';
                    $detailHtml .= '<thead class="table-secondary"><tr><th>Bill No</th><th>Total</th><th>Paid</th><th>Due</th><th>Due Date</th><th>Days Overdue</th><th>Status</th></tr></thead><tbody>';
                    foreach ($c['bills'] as $b) {
                        $detailHtml .= '<tr>';
                        $detailHtml .= '<td><a href="' . ADMIN_URL . 'billing/view.php?id=' . $b['bill_id'] . '" class="text-decoration-none">' . escape($b['bill_no']) . '</a></td>';
                        $detailHtml .= '<td class="text-end">' . format_currency($b['total_amount']) . '</td>';
                        $detailHtml .= '<td class="text-end">' . format_currency($b['paid_amount']) . '</td>';
                        $detailHtml .= '<td class="text-end">' . format_currency($b['due_amount']) . '</td>';
                        $detailHtml .= '<td>' . format_date($b['due_date']) . '</td>';
                        $detailHtml .= '<td>' . $b['days_overdue'] . ' days</td>';
                        $detailHtml .= '<td>' . get_status_badge($b['status']) . '</td>';
                        $detailHtml .= '</tr>';
                    }
                    $detailHtml .= '</tbody></table></div>';
                    ?>
                    <tr class="defaulter-row">
                        <td class="details-control text-center" style="cursor:pointer">
                            <i class="fas fa-plus-circle text-primary"></i>
                        </td>
                        <td><?= escape($c['consumer_no']) ?></td>
                        <td><strong><?= escape($c['full_name']) ?></strong></td>
                        <td><?= escape($c['mobile'] ?? '-') ?></td>
                        <td>Ward <?= escape($c['ward_no']) ?></td>
                        <td class="text-end fw-semibold text-danger"><?= format_currency($c['total_due']) ?></td>
                        <td class="text-center"><?= $c['bill_count'] ?></td>
                        <td class="text-center"><span class="badge bg-<?= $c['max_months_overdue'] >= 6 ? 'danger' : ($c['max_months_overdue'] >= 3 ? 'warning' : 'secondary') ?>"><?= $c['max_months_overdue'] ?> mo</span></td>
                        <td><?= format_date($c['oldest_due_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/BillingEngine.php';

Auth::requireAuth();

$pageTitle = 'Billing Settings';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Settings']
];

$error = '';
$success = '';

if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');

        $settings = [
            'billing_cycle_days' => intval(post('billing_cycle_days', 30)),
            'due_date_days' => intval(post('due_date_days', 15)),
            'penalty_percent' => floatval(post('penalty_percent', 5)),
            'vat_percent' => floatval(post('vat_percent', 0)),
            'meter_rent' => floatval(post('meter_rent', 50)),
            'sewerage_fee' => floatval(post('sewerage_fee', 0)),
            'min_units' => intval(post('min_units', 10)),
            'min_charge' => floatval(post('min_charge', 150)),
            'rate_per_unit' => floatval(post('rate_per_unit', 10)),
        ];

        foreach ($settings as $key => $value) {
            $existing = db()->fetchColumn("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                db()->query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
            } else {
                db()->insert('system_settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'billing']);
            }
        }

        log_activity(Auth::id(), 'update_settings', 'billing', 'Updated billing settings');
        alert_success('Billing settings updated successfully');
        redirect(ADMIN_URL . 'billing/settings.php');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$settings = BillingEngine::getSettings();
$fiscalYears = db()->fetchAll("SELECT * FROM fiscal_years ORDER BY start_date DESC");

$extraJs = <<<JS
<script>
$(function() {
    $('#showAdvanced').on('click', function() {
        $('#advancedSettings').toggleClass('d-none');
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    $('#minUnits, #minCharge, #ratePerUnit').on('input', function() {
        var units = parseInt($('#minUnits').val()) || 10;
        var minCharge = parseFloat($('#minCharge').val()) || 150;
        var rate = parseFloat($('#ratePerUnit').val()) || 10;
        var examples = [5, 10, 11, 15, 20, 25, 30];
        var html = '';
        examples.forEach(function(u) {
            var amount = u <= units ? minCharge : minCharge + ((u - units) * rate);
            html += '<tr><td>' + u + '</td><td>' + amount.toFixed(2) + '</td></tr>';
        });
        $('#previewTable tbody').html(html);
    }).trigger('input');
});
</script>
JS;

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-cog me-2 text-primary"></i>Billing Settings
        </h4>
        <a href="<?= ADMIN_URL ?>billing/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Bills
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>
<?= display_alert() ?>

<div class="row">
    <div class="col-lg-8">
        <form method="post">
            <?= csrf_field() ?>

            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Tariff Calculation Rules</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Minimum Units <span class="text-danger">*</span></label>
                            <input type="number" name="min_units" id="minUnits" class="form-control" value="<?= $settings['min_units'] ?>" min="0" step="1">
                            <div class="form-text">Free allowance units before per-unit charge applies</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Charge (NRs.) <span class="text-danger">*</span></label>
                            <input type="number" name="min_charge" id="minCharge" class="form-control" value="<?= $settings['min_charge'] ?>" min="0" step="0.01">
                            <div class="form-text">Flat rate for consumption up to minimum units</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rate Per Additional Unit (NRs.) <span class="text-danger">*</span></label>
                            <input type="number" name="rate_per_unit" id="ratePerUnit" class="form-control" value="<?= $settings['rate_per_unit'] ?>" min="0" step="0.01">
                            <div class="form-text">Charge per unit above minimum</div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <strong>Formula:</strong> If units &le; <?= $settings['min_units'] ?>: NRs. <?= $settings['min_charge'] ?>.
                        If units &gt; <?= $settings['min_units'] ?>: NRs. <?= $settings['min_charge'] ?> + ((units - <?= $settings['min_units'] ?>) &times; NRs. <?= $settings['rate_per_unit'] ?>)
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Billing Cycle & Due Dates</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Billing Cycle Days <span class="text-danger">*</span></label>
                            <input type="number" name="billing_cycle_days" class="form-control" value="<?= $settings['billing_cycle_days'] ?>" min="1" max="365">
                            <div class="form-text">Number of days between billing cycles (default: 30)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date Days <span class="text-danger">*</span></label>
                            <input type="number" name="due_date_days" class="form-control" value="<?= $settings['due_date_days'] ?>" min="1" max="365">
                            <div class="form-text">Days from billing end date to due date</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Late Penalty (%) <span class="text-danger">*</span></label>
                            <input type="number" name="penalty_percent" class="form-control" value="<?= $settings['penalty_percent'] ?>" min="0" max="100" step="0.01">
                            <div class="form-text">Penalty percentage applied after due date</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <a href="#" id="showAdvanced" class="text-decoration-none">
                        <h5 class="card-title mb-0">
                            Additional Charges
                            <i class="fas fa-chevron-down ms-2 small"></i>
                        </h5>
                    </a>
                </div>
                <div class="card-body d-none" id="advancedSettings">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Meter Rent (NRs./month)</label>
                            <input type="number" name="meter_rent" class="form-control" value="<?= $settings['meter_rent'] ?>" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sewerage Fee (NRs./month)</label>
                            <input type="number" name="sewerage_fee" class="form-control" value="<?= $settings['sewerage_fee'] ?>" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">VAT (%)</label>
                            <input type="number" name="vat_percent" class="form-control" value="<?= $settings['vat_percent'] ?>" min="0" max="100" step="0.01">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-1"></i> Save Settings
            </button>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">Live Tariff Preview</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="previewTable">
                        <thead class="table-light">
                            <tr><th>Units</th><th>Amount (NRs.)</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">Current Fiscal Year</h5></div>
            <div class="card-body">
                <?php
                $currentFy = db()->fetchOne("SELECT * FROM fiscal_years WHERE is_current = 1 AND status = 'active' LIMIT 1");
                if ($currentFy): ?>
                <p><strong><?= escape($currentFy['label']) ?></strong></p>
                <p class="small text-muted mb-0">
                    <?= format_date($currentFy['start_date']) ?> - <?= format_date($currentFy['end_date']) ?>
                </p>
                <?php else: ?>
                <p class="text-warning mb-0">No active fiscal year set</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Quick Actions</h5></div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= ADMIN_URL ?>billing/tariffs.php" class="btn btn-outline-primary">
                        <i class="fas fa-tags me-1"></i> Manage Tariffs
                    </a>
                    <a href="<?= ADMIN_URL ?>billing/generate.php" class="btn btn-outline-success">
                        <i class="fas fa-plus-circle me-1"></i> Generate Bills
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

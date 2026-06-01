<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/WorkflowEngine.php';
$pageTitle = 'Payment Gateways';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Payment Gateways'],
];
RBAC::requirePermission('settings.edit');
$error = '';
$success = '';

if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');
        $ids = post('gateway_id', []);
        $names = post('gateway_name', []);
        $merchants = post('merchant_id', []);
        $secrets = post('secret_key', []);
        $apiKeys = post('api_key', []);
        $apiUrls = post('api_url', []);
        $active = post('is_active', []);
        $testMode = post('is_test_mode', []);

        foreach ($ids as $i => $id) {
            WorkflowEngine::updateGatewayConfig(intval($id), [
                'gateway_name' => $names[$i] ?? '',
                'merchant_id' => $merchants[$i] ?? '',
                'secret_key' => $secrets[$i] ?? '',
                'api_key' => $apiKeys[$i] ?? '',
                'api_url' => $apiUrls[$i] ?? '',
                'is_active' => in_array($id, $active) ? 1 : 0,
                'is_test_mode' => in_array($id, $testMode) ? 1 : 0,
            ]);
        }
        $success = 'Gateway configurations updated';
        redirect(ADMIN_URL . 'billing/gateways.php');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$gateways = db()->fetchAll("SELECT * FROM payment_gateways ORDER BY gateway_type");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-credit-card me-2 text-primary"></i>Payment Gateways</h4>
        <p class="text-muted mb-0">Configure online payment gateways (eSewa, Khalti, Fonepay, QR)</p>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="row g-4">
        <?php foreach ($gateways as $gw): ?>
        <input type="hidden" name="gateway_id[]" value="<?= $gw['id'] ?>">
        <input type="hidden" name="gateway_name[]" value="<?= escape($gw['gateway_name']) ?>">
        <div class="col-md-6">
            <div class="card h-100 <?= $gw['is_active'] ? 'border-success' : '' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php
                        $icons = ['esewa' => 'money-bill-wave', 'khalti' => 'mobile-alt', 'fonepay' => 'university', 'qr' => 'qrcode'];
                        $icon = $icons[$gw['gateway_type']] ?? 'credit-card';
                        ?>
                        <i class="fas fa-<?= $icon ?> me-2 text-<?= $gw['is_active'] ? 'success' : 'muted' ?>"></i>
                        <?= escape($gw['gateway_name']) ?>
                        <span class="badge bg-<?= $gw['is_active'] ? 'success' : 'secondary' ?> ms-2"><?= $gw['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </h5>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active[]" value="<?= $gw['id'] ?>" id="active_<?= $gw['id'] ?>" <?= $gw['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="active_<?= $gw['id'] ?>">Active</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Merchant ID</label>
                            <input type="text" name="merchant_id[<?= $gw['id'] ?>]" class="form-control" value="<?= escape($gw['merchant_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secret Key</label>
                            <input type="password" name="secret_key[<?= $gw['id'] ?>]" class="form-control" value="<?= escape($gw['secret_key'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API Key</label>
                            <input type="text" name="api_key[<?= $gw['id'] ?>]" class="form-control" value="<?= escape($gw['api_key'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API URL</label>
                            <input type="url" name="api_url[<?= $gw['id'] ?>]" class="form-control" value="<?= escape($gw['api_url'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_test_mode[]" value="<?= $gw['id'] ?>" id="test_<?= $gw['id'] ?>" <?= $gw['is_test_mode'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="test_<?= $gw['id'] ?>">Test Mode (Sandbox)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i class="fas fa-save me-2"></i>Save Gateway Configurations
        </button>
    </div>
</form>

<div class="card mt-4">
    <div class="card-header"><h5><i class="fas fa-info-circle me-2 text-primary"></i>Gateway Setup Guide</h5></div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                    <h6>eSewa</h6>
                    <small class="text-muted">
                        Test: https://rc-epay.esewa.com.np/api/epay/main/v2/form<br>
                        Live: https://epay.esewa.com.np/api/epay/main/v2/form<br>
                        Register at esewa.com.np
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-mobile-alt fa-2x text-primary mb-2"></i>
                    <h6>Khalti</h6>
                    <small class="text-muted">
                        API: https://khalti.com/api/v2/payment/verify/<br>
                        Dashboard: admin.khalti.com<br>
                        Get merchant ID from Khalti
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-university fa-2x text-warning mb-2"></i>
                    <h6>FonePay</h6>
                    <small class="text-muted">
                        Integration with Nepal Clearing House<br>
                        Requires merchant registration<br>
                        Contact FonePay for credentials
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-qrcode fa-2x text-info mb-2"></i>
                    <h6>QR Payment</h6>
                    <small class="text-muted">
                        Generate QR codes for<br>
                        eSewa/Khalti/FonePay<br>
                        Uses gateway merchant IDs
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'System Settings';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Settings']
];
RBAC::requirePermission('settings.view');
require_once __DIR__ . '/../includes/header.php';

$activeTab = get('tab', 'general');
$currentUser = Auth::user();

// Handle POST saves
if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $tab = post('tab', 'general');

        if ($tab === 'general') {
            RBAC::requirePermission('settings.edit');
            $settings = [
                'site_name' => post('site_name'),
                'site_description' => post('site_description'),
                'default_page_size' => post('default_page_size', 25),
                'date_format' => post('date_format'),
                'timezone' => post('timezone'),
                'maintenance_mode' => post('maintenance_mode', 0),
                'default_currency' => post('default_currency', 'NRs.'),
            ];
            foreach ($settings as $key => $value) {
                db()->query(
                    "INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at)
                     VALUES (:key, :value, :group, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = NOW()",
                    ['key' => $key, 'value' => $value, 'group' => 'general', 'value2' => $value]
                );
            }
            log_activity(Auth::id(), 'update', 'Settings', 'Updated general settings');
            alert_success('General settings saved successfully');
        }

        if ($tab === 'organization') {
            RBAC::requirePermission('settings.edit');
            $orgData = [
                'name' => post('org_name'),
                'short_name' => post('short_name'),
                'registration_no' => post('registration_no'),
                'pan_no' => post('pan_no'),
                'address' => post('address'),
                'ward_no' => post('ward_no'),
                'municipality' => post('municipality'),
                'district' => post('district'),
                'province' => post('province'),
                'phone' => post('phone'),
                'email' => post('email'),
                'website' => post('website'),
            ];

            // Logo upload
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = upload_file($_FILES['logo'], UPLOADS_PATH . 'organization', ['jpg', 'jpeg', 'png', 'gif']);
                if ($uploadResult) {
                    $orgData['logo'] = $uploadResult;
                }
            }

            $existing = db()->fetchOne("SELECT id FROM organizations LIMIT 1");
            if ($existing) {
                db()->update('organizations', $orgData, 'id = :id', ['id' => $existing['id']]);
            } else {
                $orgData['created_at'] = date('Y-m-d H:i:s');
                db()->insert('organizations', $orgData);
            }

            // Also save fiscal year start/end dates for the org
            db()->query(
                "INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at)
                 VALUES (:key1, :val1, 'billing', NOW())
                 ON DUPLICATE KEY UPDATE setting_value = :val1b, updated_at = NOW()",
                ['key1' => 'fiscal_year_start', 'val1' => post('fiscal_year_start', '01-17'), 'val1b' => post('fiscal_year_start', '01-17')]
            );
            db()->query(
                "INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at)
                 VALUES (:key2, :val2, 'billing', NOW())
                 ON DUPLICATE KEY UPDATE setting_value = :val2b, updated_at = NOW()",
                ['key2' => 'fiscal_year_end', 'val2' => post('fiscal_year_end', '07-16'), 'val2b' => post('fiscal_year_end', '07-16')]
            );

            log_activity(Auth::id(), 'update', 'Settings', 'Updated organization profile');
            alert_success('Organization profile saved successfully');
        }

        if ($tab === 'billing') {
            RBAC::requirePermission('settings.edit');
            $settings = [
                'default_currency' => post('default_currency', 'NRs.'),
                'billing_cycle_days' => post('billing_cycle_days', 30),
                'due_date_days' => post('due_date_days', 15),
                'penalty_percent' => post('penalty_percent', 5),
                'vat_percent' => post('vat_percent', 0),
                'meter_rent' => post('meter_rent', 50),
                'sewerage_fee' => post('sewerage_fee', 0),
            ];
            foreach ($settings as $key => $value) {
                db()->query(
                    "INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at)
                     VALUES (:key, :value, 'billing', NOW())
                     ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = NOW()",
                    ['key' => $key, 'value' => $value, 'value2' => $value]
                );
            }
            log_activity(Auth::id(), 'update', 'Settings', 'Updated billing settings');
            alert_success('Billing settings saved successfully');
        }

        if ($tab === 'fiscal_years') {
            RBAC::requirePermission('settings.edit');
            $action = post('fy_action', 'create');

            if ($action === 'create') {
                $yearCode = post('year_code');
                if (!empty($yearCode)) {
                    $exists = db()->exists('fiscal_years', 'year_code = :code', ['code' => $yearCode]);
                    if (!$exists) {
                        db()->insert('fiscal_years', [
                            'year_code' => $yearCode,
                            'label' => post('label', $yearCode),
                            'start_date' => post('start_date'),
                            'end_date' => post('end_date'),
                            'is_current' => 0,
                            'status' => 'active',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        log_activity(Auth::id(), 'create', 'Settings', "Created fiscal year: {$yearCode}");
                        alert_success('Fiscal year created successfully');
                    } else {
                        alert_error('Fiscal year code already exists');
                    }
                }
            } elseif ($action === 'set_current') {
                $fyId = post('fy_id');
                if ($fyId) {
                    db()->update('fiscal_years', ['is_current' => 0], '1=1');
                    db()->update('fiscal_years', ['is_current' => 1], 'id = :id', ['id' => $fyId]);
                    log_activity(Auth::id(), 'update', 'Settings', "Set current fiscal year to ID: {$fyId}");
                    alert_success('Current fiscal year updated');
                }
            } elseif ($action === 'close') {
                $fyId = post('fy_id');
                if ($fyId) {
                    db()->update('fiscal_years', ['status' => 'closed'], 'id = :id', ['id' => $fyId]);
                    log_activity(Auth::id(), 'update', 'Settings', "Closed fiscal year ID: {$fyId}");
                    alert_success('Fiscal year closed');
                }
            }
        }

        // Redirect to same tab
        redirect(ADMIN_URL . 'settings/index.php?tab=' . $tab);
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
}

// Load settings
$allSettings = db()->fetchAll("SELECT setting_key, setting_value FROM system_settings");
$settingsMap = [];
foreach ($allSettings as $s) {
    $settingsMap[$s['setting_key']] = $s['setting_value'];
}

// Load organization
$organization = db()->fetchOne("SELECT * FROM organizations LIMIT 1") ?: [];

// Load fiscal years
$fiscalYears = db()->fetchAll("SELECT * FROM fiscal_years ORDER BY start_date DESC");

// Helper
function setting($key, $default = '') {
    global $settingsMap;
    return $settingsMap[$key] ?? $default;
}

$extraCss = '<style>
.setting-tab-pane { display: none; }
.setting-tab-pane.active { display: block; }
.logo-preview { max-width: 200px; max-height: 100px; object-fit: contain; border: 1px solid #dee2e6; border-radius: 8px; padding: 4px; }
</style>';

$extraJs = <<<JS
<script>
$(document).ready(function() {
    $('.nav-link[data-tab]').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        $('.setting-tab-pane').removeClass('active');
        $('#tab-' + tab).addClass('active');
        history.replaceState(null, null, '?tab=' + tab);
    });

    // Logo preview
    $('#logoInput').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logoPreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
JS;
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>System Settings</h4>
            <p>Configure system-wide settings and preferences</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>settings/backup.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-database me-1"></i> Backup
            </a>
            <a href="<?= ADMIN_URL ?>settings/audit-logs.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-history me-1"></i> Audit Logs
            </a>
            <a href="<?= ADMIN_URL ?>settings/system-info.php" class="btn btn-outline-info btn-sm">
                <i class="fas fa-info-circle me-1"></i> System Info
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs m-0 px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" data-tab="general" href="#">
                    <i class="fas fa-cog me-1"></i> General
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'organization' ? 'active' : '' ?>" data-tab="organization" href="#">
                    <i class="fas fa-building me-1"></i> Organization
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'billing' ? 'active' : '' ?>" data-tab="billing" href="#">
                    <i class="fas fa-file-invoice-dollar me-1"></i> Billing
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'fiscal_years' ? 'active' : '' ?>" data-tab="fiscal_years" href="#">
                    <i class="fas fa-calendar-alt me-1"></i> Fiscal Years
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">

        <!-- General Settings -->
        <div class="setting-tab-pane <?= $activeTab === 'general' ? 'active' : '' ?>" id="tab-general">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="general">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?= escape(setting('site_name', APP_NAME)) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Site Description</label>
                        <input type="text" name="site_description" class="form-control" value="<?= escape(setting('site_description')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Default Page Size</label>
                        <select name="default_page_size" class="form-select">
                            <?php foreach ([10, 25, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= setting('default_page_size', 25) == $size ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Format</label>
                        <select name="date_format" class="form-select">
                            <?php foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'] as $fmt): ?>
                            <option value="<?= $fmt ?>" <?= setting('date_format', 'Y-m-d') === $fmt ? 'selected' : '' ?>><?= escape($fmt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php $tz = setting('timezone', 'Asia/Kathmandu'); ?>
                            <option value="Asia/Kathmandu" <?= $tz === 'Asia/Kathmandu' ? 'selected' : '' ?>>Asia/Kathmandu (UTC+5:45)</option>
                            <option value="Asia/Kolkata" <?= $tz === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (UTC+5:30)</option>
                            <option value="UTC" <?= $tz === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" name="default_currency" class="form-control" value="<?= escape(setting('default_currency', 'NRs.')) ?>">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="mmSwitch" <?= setting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mmSwitch">Maintenance Mode <span class="text-muted small">(blocks non-admin access)</span></label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" <?= RBAC::can('settings.edit') ? '' : 'disabled' ?>>
                            <i class="fas fa-save me-1"></i> Save General Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Organization Settings -->
        <div class="setting-tab-pane <?= $activeTab === 'organization' ? 'active' : '' ?>" id="tab-organization">
            <form method="post" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="organization">

                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Organization Name <span class="text-danger">*</span></label>
                                <input type="text" name="org_name" class="form-control" value="<?= escape($organization['name'] ?? APP_ORG) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Short Name</label>
                                <input type="text" name="short_name" class="form-control" value="<?= escape($organization['short_name'] ?? APP_SHORT) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Registration No</label>
                                <input type="text" name="registration_no" class="form-control" value="<?= escape($organization['registration_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">PAN / VAT No</label>
                                <input type="text" name="pan_no" class="form-control" value="<?= escape($organization['pan_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= escape($organization['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= escape($organization['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Website</label>
                                <input type="url" name="website" class="form-control" value="<?= escape($organization['website'] ?? '') ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" value="<?= escape($organization['address'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ward No</label>
                                <input type="number" name="ward_no" class="form-control" value="<?= escape($organization['ward_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Municipality</label>
                                <select name="municipality" class="form-select">
                                    <option value="">Select Municipality</option>
                                    <?php foreach (get_municipality_options() as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($organization['municipality'] ?? '') === $val ? 'selected' : '' ?>><?= escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">District</label>
                                <select name="district" class="form-select">
                                    <option value="">Select District</option>
                                    <?php foreach (get_district_options() as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($organization['district'] ?? '') === $val ? 'selected' : '' ?>><?= escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Province</label>
                                <select name="province" class="form-select">
                                    <option value="">Select Province</option>
                                    <?php foreach (get_province_options() as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($organization['province'] ?? '') === $val ? 'selected' : '' ?>><?= escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fiscal Year Start (MM-DD)</label>
                                <input type="text" name="fiscal_year_start" class="form-control" value="<?= escape($organization['fiscal_year_start'] ?? setting('fiscal_year_start', '01-17')) ?>" placeholder="MM-DD">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fiscal Year End (MM-DD)</label>
                                <input type="text" name="fiscal_year_end" class="form-control" value="<?= escape($organization['fiscal_year_end'] ?? setting('fiscal_year_end', '07-16')) ?>" placeholder="MM-DD">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Organization Logo</label>
                        <div class="border rounded p-3 text-center bg-light">
                            <?php if (!empty($organization['logo'])): ?>
                            <img id="logoPreview" class="logo-preview mb-2" src="<?= UPLOAD_URL ?>organization/<?= escape($organization['logo']) ?>" alt="Logo">
                            <?php else: ?>
                            <img id="logoPreview" class="logo-preview mb-2" style="display:none" src="" alt="Logo">
                            <?php endif; ?>
                            <div class="mb-2">
                                <i class="fas fa-image fa-3x text-muted" id="logoPlaceholder" style="<?= !empty($organization['logo']) ? 'display:none' : '' ?>"></i>
                            </div>
                            <input type="file" name="logo" id="logoInput" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted">JPG, PNG or GIF. Max 2MB</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" <?= RBAC::can('settings.edit') ? '' : 'disabled' ?>>
                            <i class="fas fa-save me-1"></i> Save Organization
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Billing Settings -->
        <div class="setting-tab-pane <?= $activeTab === 'billing' ? 'active' : '' ?>" id="tab-billing">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="billing">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Default Currency</label>
                        <input type="text" name="default_currency" class="form-control" value="<?= escape(setting('default_currency', 'NRs.')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Billing Cycle (Days)</label>
                        <input type="number" name="billing_cycle_days" class="form-control" value="<?= escape(setting('billing_cycle_days', 30)) ?>" min="1" max="365">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Due Date (Days after billing)</label>
                        <input type="number" name="due_date_days" class="form-control" value="<?= escape(setting('due_date_days', 15)) ?>" min="1" max="180">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Penalty (%)</label>
                        <input type="number" name="penalty_percent" class="form-control" value="<?= escape(setting('penalty_percent', 5)) ?>" step="0.01" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">VAT (%)</label>
                        <input type="number" name="vat_percent" class="form-control" value="<?= escape(setting('vat_percent', 0)) ?>" step="0.01" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Meter Rent (<?= escape(setting('default_currency', 'NRs.')) ?>)</label>
                        <input type="number" name="meter_rent" class="form-control" value="<?= escape(setting('meter_rent', 50)) ?>" step="0.01" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sewerage Fee (<?= escape(setting('default_currency', 'NRs.')) ?>)</label>
                        <input type="number" name="sewerage_fee" class="form-control" value="<?= escape(setting('sewerage_fee', 0)) ?>" step="0.01" min="0">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" <?= RBAC::can('settings.edit') ? '' : 'disabled' ?>>
                            <i class="fas fa-save me-1"></i> Save Billing Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Fiscal Years -->
        <div class="setting-tab-pane <?= $activeTab === 'fiscal_years' ? 'active' : '' ?>" id="tab-fiscal_years">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="card border">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-plus-circle me-1"></i> Add Fiscal Year</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="tab" value="fiscal_years">
                                <input type="hidden" name="fy_action" value="create">
                                <div class="mb-3">
                                    <label class="form-label">Year Code <span class="text-danger">*</span></label>
                                    <input type="text" name="year_code" class="form-control" placeholder="e.g. 2082-83" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Label</label>
                                    <input type="text" name="label" class="form-control" placeholder="e.g. Fiscal Year 2082/83">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" <?= RBAC::can('settings.edit') ? '' : 'disabled' ?>>
                                    <i class="fas fa-plus me-1"></i> Create Fiscal Year
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Year Code</th>
                                    <th>Label</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Status</th>
                                    <th style="width:120px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($fiscalYears)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No fiscal years defined</td></tr>
                                <?php else: ?>
                                <?php foreach ($fiscalYears as $fy): ?>
                                <tr class="<?= $fy['is_current'] ? 'table-primary' : '' ?>">
                                    <td><strong><?= escape($fy['year_code']) ?></strong></td>
                                    <td><?= escape($fy['label']) ?></td>
                                    <td><?= format_date($fy['start_date']) ?></td>
                                    <td><?= format_date($fy['end_date']) ?></td>
                                    <td>
                                        <?php if ($fy['is_current']): ?>
                                        <span class="badge bg-success">Current</span>
                                        <?php else: ?>
                                        <span class="badge bg-<?= $fy['status'] === 'active' ? 'info' : 'secondary' ?>"><?= escape(ucfirst($fy['status'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$fy['is_current'] && $fy['status'] === 'active' && RBAC::can('settings.edit')): ?>
                                            <form method="post" action="" class="d-inline" onsubmit="return confirm('Set this as current fiscal year?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="tab" value="fiscal_years">
                                                <input type="hidden" name="fy_action" value="set_current">
                                                <input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm" title="Set as Current">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="" class="d-inline" onsubmit="return confirm('Close this fiscal year?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="tab" value="fiscal_years">
                                                <input type="hidden" name="fy_action" value="close">
                                                <input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" title="Close Year" <?= $fy['status'] === 'closed' ? 'disabled' : '' ?>>
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

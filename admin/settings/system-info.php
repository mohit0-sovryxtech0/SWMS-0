<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'System Information';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Settings', 'url' => ADMIN_URL . 'settings/index.php'],
    ['label' => 'System Info']
];
RBAC::requirePermission('settings.view');
require_once __DIR__ . '/../includes/header.php';

$serverInfo = [
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'PHP Version' => phpversion(),
    'PHP SAPI' => php_sapi_name(),
    'Server Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
    'Host Name' => php_uname('n'),
    'Operating System' => php_uname('s') . ' ' . php_uname('r'),
    'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Current Time' => date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')',
];

$requiredExts = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'session', 'gd', 'mbstring', 'curl', 'openssl', 'zip', 'xml'];
$phpExtensions = [];
foreach ($requiredExts as $ext) {
    $phpExtensions[$ext] = extension_loaded($ext);
}

$phpSettings = [
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
    'max_input_time' => ini_get('max_input_time') . 's',
    'max_input_vars' => ini_get('max_input_vars'),
    'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
    'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off',
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'On' : 'Off',
    'date.timezone' => ini_get('date.timezone'),
    'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime') . 's',
];

$dbInfo = [];
$tables = [];
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $dbInfo['Server Version'] = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
    $dbInfo['Client Version'] = $conn->getAttribute(PDO::ATTR_CLIENT_VERSION);
    $dbInfo['Connection Status'] = 'Connected';
    $dbInfo['Database Name'] = DB_NAME;
    $dbInfo['Host'] = DB_HOST . ':' . DB_PORT;
    $dbInfo['Charset'] = DB_CHARSET;

    $tables = $db->fetchAll(
        "SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS `rows`,
                ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) AS size_kb
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :db AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME", ['db' => DB_NAME]
    );
    $totalSizeKb = array_sum(array_column($tables, 'size_kb'));
    $totalRows = array_sum(array_column($tables, 'rows'));
    $dbInfo['Total Tables'] = count($tables);
    $dbInfo['Total Rows'] = number_format($totalRows);
    $dbInfo['Total Size'] = $totalSizeKb > 1024 ? round($totalSizeKb / 1024, 2) . ' MB' : $totalSizeKb . ' KB';
} catch (Exception $e) {
    $dbInfo['Connection Status'] = 'Error: ' . $e->getMessage();
}

$appInfo = [
    'Application Name' => APP_NAME,
    'Short Name' => APP_SHORT,
    'Version' => APP_VERSION,
    'Organization' => APP_ORG,
    'Country' => APP_COUNTRY,
    'Base URL' => BASE_URL,
    'Session Lifetime' => SESSION_LIFETIME . 's (' . round(SESSION_LIFETIME / 3600, 1) . ' hours)',
];

$counts = [
    'Registered Users' => db()->fetchColumn("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL"),
    'Active Consumers' => db()->fetchColumn("SELECT COUNT(*) FROM consumers WHERE status = 'active' AND deleted_at IS NULL"),
    'Total Bills' => db()->fetchColumn("SELECT COUNT(*) FROM bills WHERE deleted_at IS NULL"),
    'Total Payments' => db()->fetchColumn("SELECT COUNT(*) FROM payments WHERE status = 'completed'"),
    'Open Complaints' => db()->fetchColumn("SELECT COUNT(*) FROM complaints WHERE status IN ('open','in_progress') AND deleted_at IS NULL"),
    'Total Assets' => db()->fetchColumn("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL"),
    'Total Employees' => db()->fetchColumn("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL"),
    'Fiscal Years' => db()->fetchColumn("SELECT COUNT(*) FROM fiscal_years"),
];

ob_start();
phpinfo();
$phpInfoContent = ob_get_clean();
$phpInfoContent = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpInfoContent);
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>System Information</h4>
            <p>Technical details for troubleshooting and diagnostics</p>
        </div>
        <a href="<?= ADMIN_URL ?>settings/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Settings
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- App Info -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-cube me-2 text-primary"></i> Application</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <?php foreach ($appInfo as $key => $val): ?>
                        <tr><td style="width:180px" class="text-muted"><?= escape($key) ?></td><td><strong><?= escape($val) ?></strong></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <hr>
                <h6>System Records</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <?php foreach ($counts as $key => $val): ?>
                        <tr><td style="width:180px" class="text-muted"><?= escape($key) ?></td><td><strong><?= number_format($val) ?></strong></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Server Info -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-server me-2 text-success"></i> Server</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <?php foreach ($serverInfo as $key => $val): ?>
                        <tr><td style="width:180px" class="text-muted"><?= escape($key) ?></td><td><strong><?= escape($val) ?></strong></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Database Info -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-database me-2 text-info"></i> Database</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <?php foreach ($dbInfo as $key => $val): ?>
                        <tr><td style="width:180px" class="text-muted"><?= escape($key) ?></td><td><strong><?= escape($val) ?></strong></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($tables)): ?>
                <hr>
                <h6>Tables <small class="text-muted">(<?= count($tables) ?> total)</small></h6>
                <div style="max-height:250px;overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Table</th><th>Engine</th><th>Rows</th><th>Size</th></tr></thead>
                        <tbody>
                            <?php foreach ($tables as $t): ?>
                            <tr>
                                <td><code><?= escape($t['name']) ?></code></td>
                                <td><?= escape($t['engine']) ?></td>
                                <td><?= number_format($t['rows']) ?></td>
                                <td><?= $t['size_kb'] ?> KB</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PHP Info -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fab fa-php me-2 text-primary"></i> PHP Configuration</h6></div>
            <div class="card-body">
                <h6>Required Extensions</h6>
                <div class="mb-3">
                    <?php foreach ($phpExtensions as $ext => $loaded): ?>
                    <span class="badge bg-<?= $loaded ? 'success' : 'danger' ?> me-1 mb-1">
                        <?= escape($ext) ?> <?= $loaded ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>' ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <h6>Settings</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <?php foreach ($phpSettings as $key => $val): ?>
                        <tr><td style="width:180px" class="text-muted"><?= escape($key) ?></td><td><strong><?= escape($val) ?></strong></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <hr>
                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#phpInfoCollapse">
                    <i class="fas fa-info-circle me-1"></i> View Full PHP Info
                </button>
                <div class="collapse mt-2" id="phpInfoCollapse">
                    <div style="max-height:400px;overflow:auto;border:1px solid #dee2e6;border-radius:6px;padding:8px;background:#f8f9fa;font-size:11px;">
                        <?= $phpInfoContent ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

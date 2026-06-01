<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Backup Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Settings', 'url' => ADMIN_URL . 'settings/index.php'],
    ['label' => 'Backup']
];
RBAC::requirePermission('backup.manage');
require_once __DIR__ . '/../includes/header.php';

$backupDir = ROOT_PATH . 'backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Create backup
if (isPost() && post('action') === 'create_backup') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $filename = DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;

        // Log backup start
        $backupId = db()->insert('backup_logs', [
            'backup_type' => 'manual',
            'file_name' => $filename,
            'status' => 'in_progress',
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Build mysqldump command
        $cmd = sprintf(
            '"%smysqldump" --host=%s --port=%s --user=%s --password=%s --routines --triggers --single-transaction --databases %s > "%s" 2>&1',
            '', // Add MySQL bin path if needed e.g., 'C:\\xampp\\mysql\\bin\\'
            DB_HOST,
            DB_PORT,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $filepath
        );

        // Try using MySQL command
        $output = null;
        $returnCode = null;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            // Fallback: PHP-based export using PDO
            $sql = "-- SWMS Database Backup\n";
            $sql .= "-- Host: " . DB_HOST . " | Database: " . DB_NAME . "\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
            $sql .= "USE `" . DB_NAME . "`;\n\n";

            $tables = db()->fetchAll("SHOW TABLES");
            $tableKey = "Tables_in_" . DB_NAME;

            foreach ($tables as $table) {
                $tableName = $table[$tableKey];

                // Drop and Create table
                $createTable = db()->fetchOne("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "\nDROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable['Create Table'] . ";\n\n";

                // Get data
                $rows = db()->fetchAll("SELECT * FROM `{$tableName}`");
                if (empty($rows)) continue;

                $columns = array_keys($rows[0]);
                $colStr = '`' . implode('`, `', $columns) . '`';

                $insertBatch = "INSERT INTO `{$tableName}` ({$colStr}) VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $escaped = [];
                    foreach ($row as $val) {
                        if (is_null($val)) {
                            $escaped[] = 'NULL';
                        } elseif (is_numeric($val)) {
                            $escaped[] = $val;
                        } else {
                            $escaped[] = "'" . str_replace(["'", "\\"], ["''", "\\\\"], $val) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $escaped) . ')';
                }
                $sql .= $insertBatch . implode(",\n", $values) . ";\n\n";
            }

            file_put_contents($filepath, $sql);
        }

        clearstatcache(true, $filepath);
        if (file_exists($filepath) && filesize($filepath) > 0) {
            $fileSize = filesize($filepath);
            db()->update('backup_logs', [
                'file_size' => $fileSize,
                'status' => 'success'
            ], 'id = :id', ['id' => $backupId]);

            log_activity(Auth::id(), 'create', 'Backup', "Created database backup: {$filename} ({$fileSize} bytes)");
            alert_success('Database backup created successfully (' . number_format($fileSize) . ' bytes)');
        } else {
            db()->update('backup_logs', [
                'status' => 'failed',
                'error_message' => 'Backup file is empty or could not be created'
            ], 'id = :id', ['id' => $backupId]);
            throw new Exception('Backup file could not be created. Check server permissions.');
        }
    } catch (Exception $e) {
        alert_error('Backup failed: ' . $e->getMessage());
    }

    redirect(ADMIN_URL . 'settings/backup.php');
}

// Delete backup
if (isPost() && post('action') === 'delete_backup') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $backupId = post('backup_id');
        $backup = db()->fetchOne("SELECT * FROM backup_logs WHERE id = :id", ['id' => $backupId]);

        if ($backup) {
            $filepath = $backupDir . $backup['file_name'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            db()->delete('backup_logs', 'id = :id', ['id' => $backupId]);
            log_activity(Auth::id(), 'delete', 'Backup', "Deleted backup: {$backup['file_name']}");
            alert_success('Backup deleted successfully');
        }
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
    redirect(ADMIN_URL . 'settings/backup.php');
}

// Get backup list
$backups = db()->fetchAll(
    "SELECT bl.*, u.name AS created_by_name
     FROM backup_logs bl
     LEFT JOIN users u ON bl.created_by = u.id
     ORDER BY bl.created_at DESC LIMIT 50"
);

// Calculate total backup size
$totalSize = 0;
$existingFiles = [];
foreach ($backups as $b) {
    $filepath = $backupDir . $b['file_name'];
    if (file_exists($filepath)) {
        $totalSize += $b['file_size'] ?? filesize($filepath);
        $existingFiles[$b['id']] = true;
    }
}

$extraCss = '<style>
.backup-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
</style>';
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Backup Management</h4>
            <p>Create and manage database backups</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>settings/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Settings
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Create Backup Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center p-4">
                <div class="backup-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                    <i class="fas fa-database fa-2x"></i>
                </div>
                <h5>Create New Backup</h5>
                <p class="text-muted small">Generate a complete SQL dump of the database including tables, views, and stored procedures.</p>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Start database backup? This may take a few moments.')">
                        <i class="fas fa-play me-1"></i> Create Backup Now
                    </button>
                </form>
                <hr class="my-3">
                <div class="text-start small text-muted">
                    <i class="fas fa-info-circle me-1"></i> Backup file is stored in <code>/backups/</code> directory
                </div>
                <div class="text-start small text-muted mt-1">
                    <i class="fas fa-clock me-1"></i> Estimated time varies by database size
                </div>
            </div>
        </div>

        <!-- Schedule Note -->
        <div class="card mt-3">
            <div class="card-body">
                <h6><i class="fas fa-clock me-2 text-primary"></i> Automated Backup Schedule</h6>
                <p class="small text-muted mb-2">To enable automatic scheduled backups, add a cron job (Linux) or scheduled task (Windows):</p>
                <pre class="bg-light p-2 rounded small" style="font-size:11px;"># Daily backup at 2:00 AM
0 2 * * * php <?= escape(ROOT_PATH) ?>cron/backup.php</pre>
                <p class="small text-muted mb-0">Or use MySQL Events:</p>
                <pre class="bg-light p-2 rounded small" style="font-size:11px;">CREATE EVENT daily_backup
ON SCHEDULE EVERY 1 DAY
STARTS '2026-01-01 02:00:00'
DO CALL sp_create_backup();</pre>
            </div>
        </div>
    </div>

    <!-- Backups List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2 text-primary"></i> Backup History</h5>
                <span class="badge bg-info">Total: <?= number_format($totalSize) ?> bytes (<?= count($backups) ?> backups)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($backups)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No backups created yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Date</th>
                                <th style="width:120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $i => $backup): ?>
                            <?php $fileExists = isset($existingFiles[$backup['id']]); ?>
                            <tr class="<?= $backup['status'] === 'failed' ? 'table-danger' : '' ?>">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <code style="font-size:11px;"><?= escape($backup['file_name']) ?></code>
                                </td>
                                <td><span class="badge bg-<?= $backup['backup_type'] === 'manual' ? 'info' : 'secondary' ?>"><?= escape($backup['backup_type']) ?></span></td>
                                <td>
                                    <?php if ($backup['file_size']): ?>
                                    <?= number_format($backup['file_size'] / 1024, 1) ?> KB
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($backup['status'] === 'success'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Success</span>
                                    <?php elseif ($backup['status'] === 'failed'): ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Failed</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-spinner"></i> In Progress</span>
                                    <?php endif; ?>
                                    <?php if ($backup['error_message']): ?>
                                    <br><small class="text-danger"><?= escape($backup['error_message']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($backup['created_by_name'] ?? 'System') ?></td>
                                <td style="white-space:nowrap;font-size:12px;"><?= format_datetime($backup['created_at']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($fileExists && $backup['status'] === 'success'): ?>
                                        <a href="<?= ADMIN_URL ?>settings/backup.php?download=<?= $backup['id'] ?>" class="btn btn-primary" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php endif; ?>
                                        <form method="post" action="" class="d-inline" onsubmit="return confirm('Delete this backup permanently?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="backup_id" value="<?= $backup['id'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Handle download
if (isGet() && get('download')) {
    $backupId = get('download');
    $backup = db()->fetchOne("SELECT * FROM backup_logs WHERE id = :id", ['id' => $backupId]);
    if ($backup && $backup['status'] === 'success') {
        $filepath = $backupDir . $backup['file_name'];
        if (file_exists($filepath)) {
            log_activity(Auth::id(), 'download', 'Backup', "Downloaded backup: {$backup['file_name']}");
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $backup['file_name'] . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache');
            readfile($filepath);
            exit;
        }
    }
    alert_error('Backup file not found');
    redirect(ADMIN_URL . 'settings/backup.php');
}
?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

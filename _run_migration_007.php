<?php
require_once __DIR__ . '/includes/config.php';

$migrationFile = __DIR__ . '/database/migrations/007_workflow_engine.sql';
$logFile = __DIR__ . '/logs/migration_007.log';

Auth::requireAuth();
if (!RBAC::isSuperAdmin()) {
    die('Only super admin can run migrations');
}

echo "<h2>Running Migration 007: Workflow Engine</h2><pre>";

try {
    $sql = file_get_contents($migrationFile);
    if (!$sql) throw new Exception("Could not read migration file");

    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $db = db();
    $count = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        if (empty($stmt) || str_starts_with($stmt, '--') || str_starts_with($stmt, '#')) continue;
        try {
            $db->query($stmt);
            $count++;
            echo "✓ Executed: " . substr($stmt, 0, 80) . "...\n";
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'already exists')) {
                echo "• Skipped (already exists): " . substr($stmt, 0, 60) . "...\n";
            } else {
                $errors[] = $msg;
                echo "✗ Error: {$msg}\n";
            }
        }
    }

    echo "\n---\n";
    echo "Migration completed: {$count} statements executed.\n";
    if (!empty($errors)) {
        echo "Errors: " . count($errors) . "\n";
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Migration 007: {$count} statements, " . count($errors) . " errors\n", FILE_APPEND);

    echo "\n<strong>Adding workflow permissions...</strong>\n";
    try {
        $perms = [
            ['readings.routes', 'Manage Reading Routes'],
            ['readings.schedule', 'Schedule Readings'],
            ['cycles.manage', 'Manage Billing Cycles'],
            ['gateways.configure', 'Configure Payment Gateways'],
            ['reconciliation.view', 'View Payment Reconciliation'],
            ['reconciliation.reconcile', 'Reconcile Payments'],
            ['exports.pdf', 'Export PDF'],
            ['exports.excel', 'Export Excel'],
            ['exports.csv', 'Export CSV'],
        ];
        foreach ($perms as $p) {
            $existing = $db->fetchColumn("SELECT COUNT(*) FROM permissions WHERE slug = ?", [$p[0]]);
            if (!$existing) {
                $db->insert('permissions', ['slug' => $p[0], 'name' => $p[1], 'module' => 'Workflow']);
                echo "✓ Added permission: {$p[0]} ({$p[1]})\n";
            } else {
                echo "• Permission exists: {$p[0]}\n";
            }
        }
        echo "Permissions added.\n";
    } catch (Exception $e) {
        echo "Permission error: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

echo "\n<a href='" . ADMIN_URL . "dashboard/index.php'>&larr; Back to Dashboard</a></pre>";

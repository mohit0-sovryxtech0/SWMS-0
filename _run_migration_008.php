<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireAuth();
RBAC::requirePermission('settings.edit');

$pageTitle = 'Run Migration 008 - Reading Verification & Workflow';
$migrationFile = __DIR__ . '/database/migrations/008_reading_verification_workflow.sql';

$output = '';
$hasError = false;

if (isPost() && verify_csrf(post('csrf_token'))) {
    try {
        if (!file_exists($migrationFile)) throw new Exception("Migration file not found: {$migrationFile}");
        $sql = file_get_contents($migrationFile);
        if (empty(trim($sql))) throw new Exception('Migration file is empty');

        $queries = explode(';', $sql);
        $executed = 0;
        $errors = [];

        db()->beginTransaction();
        try {
            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query) || str_starts_with($query, '--') || str_starts_with($query, '#')) continue;
                try {
                    db()->execute($query);
                    $executed++;
                } catch (Exception $e) {
                    $errors[] = "Query {$executed}: " . $e->getMessage();
                }
            }
            db()->commit();
            $output .= "Migration completed successfully. {$executed} statements executed.";
            if (!empty($errors)) {
                $output .= "\n\nWarnings (" . count($errors) . "):\n" . implode("\n", array_slice($errors, 0, 10));
                if (count($errors) > 10) $output .= "\n... and " . (count($errors) - 10) . " more";
            }
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }

        log_activity(Auth::id(), 'run_migration', 'system', 'Migration 008 executed successfully');
        $output .= "\n\nPermissions and data seeded successfully.";
    } catch (Exception $e) {
        $hasError = true;
        $output = 'Migration failed: ' . $e->getMessage();
    }
}

include_once ADMIN_PATH . 'includes/header.php';
?>
<div class="container py-4">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-database me-2 text-primary"></i>Migration 008: Reading Verification & Workflow</h4>
        </div>
        <div class="card-body">
            <p>This migration adds:</p>
            <ul>
                <li><code>reading_verifications</code> table — audit trail for approve/reject actions</li>
                <li><code>reading_documents</code> table — multiple photos per reading</li>
                <li><code>bill_notifications</code> table — SMS/email notification tracking</li>
                <li><code>payment_reconciliation</code> table — gateway transaction matching</li>
                <li>New columns on <code>meter_readings</code> — full reading status workflow</li>
                <li>New columns on <code>bills</code> — published_at, reading_id</li>
                <li>New permissions for publishing, reconciling, and verification management</li>
            </ul>

            <?php if ($output): ?>
            <div class="alert alert-<?= $hasError ? 'danger' : 'success' ?> mt-3">
                <pre class="mb-0"><?= escape($output) ?></pre>
            </div>
            <?php endif; ?>

            <form method="post" class="mt-3" onsubmit="return confirm('Run migration 008? This may take a few seconds.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><i class="fas fa-play me-1"></i>Run Migration 008</button>
                <a href="<?= ADMIN_URL ?>dashboard/index.php" class="btn btn-outline-secondary ms-2">Back to Dashboard</a>
            </form>
        </div>
    </div>
</div>
<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>

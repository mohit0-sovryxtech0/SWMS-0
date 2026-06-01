<?php
require_once __DIR__ . '/includes/config.php';

echo "<pre>Running Migration 006: Billing Engine...\n\n";

$migration = file_get_contents(__DIR__ . '/database/migrations/006_billing_engine.sql');
$statements = array_filter(array_map('trim', explode(';', $migration)));

$success = 0;
$failed = 0;

foreach ($statements as $sql) {
    if (empty($sql) || strpos($sql, '--') === 0) continue;
    try {
        db()->query($sql);
        echo "OK: " . substr($sql, 0, 80) . "...\n";
        $success++;
    } catch (Exception $e) {
        echo "SKIP: " . substr($sql, 0, 80) . "... -> " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nMigration complete: {$success} succeeded, {$failed} skipped.\n";
echo "</pre>";

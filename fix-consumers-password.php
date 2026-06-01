<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain');

try {
    $col = db()->query("SHOW COLUMNS FROM consumers WHERE Field = 'password'")->fetch();
    if ($col) {
        exit("Column 'password' already exists.\n");
    }

    // Check if 'photo' column exists to position AFTER it
    $photoCol = db()->query("SHOW COLUMNS FROM consumers WHERE Field = 'photo'")->fetch();
    $after = $photoCol ? 'photo' : 'status';

    db()->query("ALTER TABLE consumers ADD COLUMN password VARCHAR(255) AFTER {$after}");
    echo "✓ Added 'password' column to consumers table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

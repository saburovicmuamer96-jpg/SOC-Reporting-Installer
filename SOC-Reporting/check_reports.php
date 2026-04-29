<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/db.php';

header('Content-Type: text/plain');

echo "=== Report Check ===\n\n";

try {
    $db = Database::system();
    echo "✓ Connected to: " . DB_SYSTEM . "\n\n";
    
    // Check reports
    $reports = Database::fetchAll($db, "SELECT event_id, machine_slot, status, created_at FROM reports ORDER BY created_at DESC");
    echo "Found " . count($reports) . " reports:\n\n";
    
    foreach ($reports as $r) {
        echo "- {$r['event_id']} | Machine {$r['machine_slot']} | {$r['status']} | {$r['created_at']}\n";
    }
    
    echo "\n=== Database Config ===\n";
    echo "DB_SYSTEM: " . DB_SYSTEM . "\n";
    echo "DB_MACHINE_1: " . DB_MACHINE_1 . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

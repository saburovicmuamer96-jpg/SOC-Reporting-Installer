<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test system database
    $db = Database::system();
    echo "✓ System DB connected<br>";
    
    // Test query
    $users = Database::fetchAll($db, "SELECT * FROM users");
    echo "✓ Users table: " . count($users) . " users<br>";
    
    // Test machines
    $machines = Database::fetchAll($db, "SELECT * FROM machines");
    echo "✓ Machines table: " . count($machines) . " machines<br>";
    
    // Test form fields
    $fields = Database::fetchAll($db, "SELECT * FROM form_fields");
    echo "✓ Form fields: " . count($fields) . " fields<br>";
    
    // Test machine database connection
    $db1 = Database::machine(1);
    echo "✓ Machine 1 DB connected<br>";
    
    // Test reports table
    $reports = Database::fetchAll($db1, "SELECT * FROM reports");
    echo "✓ Reports table: " . count($reports) . " reports<br>";
    
    echo "<br><strong style='color: green;'>All database tests passed!</strong>";
    
} catch (PDOException $e) {
    echo "<strong style='color: red;'>PDO Error:</strong><br>";
    echo "Code: " . $e->getCode() . "<br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "<strong style='color: red;'>Error:</strong><br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

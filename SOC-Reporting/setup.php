<?php
/**
 * SOC Reporting System - Setup Script
 *
 * Run this script once to set up the databases.
 * Access via browser: http://localhost/SOC-Reporting/setup.php
 *
 * IMPORTANT: Delete this file after setup is complete!
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$results = [];
$errors = [];

// Step 1: Connect to MySQL (without database)
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, unserialize(DB_OPTIONS));
    $results[] = 'Connected to MySQL server successfully.';
} catch (PDOException $e) {
    die('<h1>Cannot connect to MySQL</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>
         <p>Check your credentials in <code>config/database.php</code></p>');
}

// Step 2: Create system database and run schema
try {
    $sql = file_get_contents(__DIR__ . '/sql/system.sql');
    $pdo->exec($sql);
    $results[] = 'System database (soc_system) created and populated.';
} catch (PDOException $e) {
    $errors[] = 'System DB error: ' . $e->getMessage();
}

// Step 3: Create 4 machine databases
$template = file_get_contents(__DIR__ . '/sql/machine_template.sql');

for ($i = 1; $i <= 4; $i++) {
    try {
        $machineSql = str_replace('{N}', (string)$i, $template);
        $pdo->exec($machineSql);
        $results[] = "Machine database (soc_machine_$i) created.";
    } catch (PDOException $e) {
        $errors[] = "Machine $i DB error: " . $e->getMessage();
    }
}

// Step 4: Verify all databases exist
$dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
$required = ['soc_system', 'soc_machine_1', 'soc_machine_2', 'soc_machine_3', 'soc_machine_4'];
$missing = array_diff($required, $dbs);

if (empty($missing)) {
    $results[] = 'All 5 databases verified successfully.';
} else {
    $errors[] = 'Missing databases: ' . implode(', ', $missing);
}

// Step 5: Verify admin user
try {
    $stmt = $pdo->query("SELECT username, role FROM soc_system.users WHERE role = 'admin' LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $results[] = "Admin user '{$admin['username']}' verified.";
    } else {
        $errors[] = 'No admin user found in soc_system.users!';
    }
} catch (PDOException $e) {
    $errors[] = 'Admin verification error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SOC Reporting - Setup</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0e17; color: #e2e8f0; padding: 40px; max-width: 700px; margin: 0 auto; }
        h1 { color: #00d4ff; font-size: 24px; }
        .result { padding: 10px 16px; margin: 6px 0; border-radius: 6px; font-size: 14px; }
        .success { background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.3); color: #00ff88; }
        .error { background: rgba(255,51,102,0.1); border: 1px solid rgba(255,51,102,0.3); color: #ff3366; }
        .warning { background: rgba(255,170,0,0.1); border: 1px solid rgba(255,170,0,0.3); color: #ffaa00; }
        .info { background: rgba(0,212,255,0.1); border: 1px solid rgba(0,212,255,0.3); color: #00d4ff; padding: 16px; margin: 20px 0; border-radius: 8px; }
        code { background: #131926; padding: 2px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; color: #00d4ff; }
        a { color: #00d4ff; }
        .btn { display: inline-block; padding: 12px 24px; background: #00d4ff; color: #0a0e17; border-radius: 8px; font-weight: 600; text-decoration: none; margin-top: 20px; }
        .btn:hover { background: #00b8e6; }
    </style>
</head>
<body>
    <h1>&#9737; SOC Reporting System - Setup</h1>

    <h2 style="font-size: 16px; color: #a0aec0;">Setup Results</h2>

    <?php foreach ($results as $r): ?>
    <div class="result success">&#10003; <?= htmlspecialchars($r) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
    <div class="result error">&#10007; <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <div class="info">
        <strong>Setup Complete!</strong><br><br>
        <strong>Default Admin Credentials:</strong><br>
        Username: <code>admin</code><br>
        Password: <code>Admin@SOC2024</code><br><br>
        <strong style="color: #ff3366;">IMPORTANT:</strong> Change the admin password immediately after first login!<br>
        <strong style="color: #ff3366;">IMPORTANT:</strong> Delete this file (<code>setup.php</code>) after setup!
    </div>

    <a href="index.php" class="btn">Open SOC Reporting System &rarr;</a>
    <?php else: ?>
    <div class="info">
        <strong>Some errors occurred.</strong> Please fix the issues above and run setup again.
    </div>
    <?php endif; ?>

    <p style="margin-top: 40px; font-size: 12px; color: #5a6578;">
        SOC Reporting System v<?= APP_VERSION ?>
    </p>
</body>
</html>

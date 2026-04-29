<?php
/**
 * SOC Reporting System - Web-based Database Installer
 * Upload this to your server and access via browser to create databases
 */

// Security check
$install_password = 'SOC@Install2026';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] !== $install_password) {
        die('Invalid password. Please check DEPLOYMENT_GUIDE.md');
    }

    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $admin_user = $_POST['admin_user'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';

    // Simplified database names
    $databases = [
        'u294365219_soc1',  // System database
        'u294365219_soc2',  // Machine 1
        'u294365219_soc3',  // Machine 2
        'u294365219_soc4',  // Machine 3
        'u294365219_soc5'   // Machine 4
    ];

    echo "<h2>Database Installation Progress</h2>";
    echo "<div style='font-family: monospace; background: #000; color: #0f0; padding: 20px;'>";

    // Try to connect with admin credentials if provided
    if ($admin_user && $admin_pass) {
        echo "Using admin credentials to create databases...<br>";
        try {
            $admin_pdo = new PDO("mysql:host=localhost", $admin_user, $admin_pass);
            $admin_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create databases
            foreach ($databases as $db) {
                try {
                    $admin_pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    echo "✓ Created database: $db<br>";
                } catch (PDOException $e) {
                    echo "⚠ Could not create $db: " . $e->getMessage() . "<br>";
                }
            }

            // Create user
            try {
                $admin_pdo->exec("CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass'");
                echo "✓ Created user: $db_user<br>";
            } catch (PDOException $e) {
                echo "⚠ User creation: " . $e->getMessage() . "<br>";
            }

            // Grant privileges
            foreach ($databases as $db) {
                try {
                    $admin_pdo->exec("GRANT ALL PRIVILEGES ON `$db`.* TO '$db_user'@'localhost'");
                    echo "✓ Granted privileges on $db<br>";
                } catch (PDOException $e) {
                    echo "⚠ Grant privileges failed: " . $e->getMessage() . "<br>";
                }
            }

            $admin_pdo->exec("FLUSH PRIVILEGES");
            echo "✓ Privileges flushed<br>";

        } catch (PDOException $e) {
            echo "✗ Admin connection failed: " . $e->getMessage() . "<br>";
            echo "<br><strong>Please create databases manually in hPanel</strong><br>";
        }
    }

    // Now import SQL schemas using the regular user credentials
    echo "<br>Importing database schemas...<br>";

    $schema_files = [
        'u294365219_soc1' => [  // System database
            'sql/system.sql',
            'sql/threat_logs.sql'
        ],
        'u294365219_soc2' => [  // Machine 1
            'sql/machine_template.sql',
            'sql/migration_add_deleted_reports.sql'
        ],
        'u294365219_soc3' => [  // Machine 2
            'sql/machine_template.sql',
            'sql/migration_add_deleted_reports.sql'
        ],
        'u294365219_soc4' => [  // Machine 3
            'sql/machine_template.sql',
            'sql/migration_add_deleted_reports.sql'
        ],
        'u294365219_soc5' => [  // Machine 4
            'sql/machine_template.sql',
            'sql/migration_add_deleted_reports.sql'
        ]
    ];

    foreach ($schema_files as $db => $files) {
        echo "<br>Setting up database: $db<br>";

        try {
            $pdo = new PDO("mysql:host=localhost;dbname=$db;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            foreach ($files as $file) {
                if (file_exists($file)) {
                    $sql = file_get_contents($file);
                    // Execute SQL in chunks to avoid issues
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    echo "  ✓ Imported: $file<br>";
                } else {
                    echo "  ⚠ File not found: $file<br>";
                }
            }

        } catch (PDOException $e) {
            echo "  ✗ Error setting up $db: " . $e->getMessage() . "<br>";
            echo "  → Make sure the database exists in hPanel first!<br>";
        }
    }

    // Update config file
    echo "<br>Updating configuration...<br>";
    $config_file = 'config/database.php';
    if (file_exists($config_file)) {
        $config = file_get_contents($config_file);
        $config = preg_replace("/define\('DB_USER',\s*'[^']*'\)/", "define('DB_USER', '$db_user')", $config);
        $config = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', '$db_pass')", $config);
        file_put_contents($config_file, $config);
        echo "✓ Configuration updated<br>";
    }

    // Run setup.php
    echo "<br>Running initial setup...<br>";
    if (file_exists('setup.php')) {
        ob_start();
        try {
            include 'setup.php';
            $output = ob_get_clean();
            echo "✓ Initial setup complete<br>";
            if ($output) {
                echo "<pre style='color: #fff;'>$output</pre>";
            }
        } catch (Exception $e) {
            ob_end_clean();
            echo "⚠ Setup error: " . $e->getMessage() . "<br>";
        }
    }

    echo "</div>";
    echo "<h3 style='color: green;'>✓ Installation Complete!</h3>";
    echo "<p><a href='index.php' style='font-size: 18px; color: #00d4ff;'>→ Access SOC Reporting System</a></p>";
    echo "<p><strong>Default Login:</strong> admin / Admin@SOC2024</p>";
    echo "<p style='color: red; font-weight: bold; padding: 10px; background: #ffe0e0; border: 2px solid red;'>
        ⚠ CRITICAL: Delete this install-soc.php file immediately after installation for security!
    </p>";

} else {
?>
<!DOCTYPE html>
<html>
<head>
    <title>SOC Reporting System - Database Installer</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            max-width: 700px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            border-bottom: 4px solid #00d4ff;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-top: 20px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #00d4ff;
        }
        button {
            width: 100%;
            padding: 15px;
            margin-top: 25px;
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.4);
        }
        .note {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
        }
        .warning {
            background: #ffe6e6;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #721c24;
            font-size: 14px;
        }
        .db-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        .db-list div {
            padding: 5px 0;
            color: #00d4ff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SOC Reporting System<br><span style="font-size: 0.6em; color: #666;">Database Installer</span></h1>

        <div class="warning">
            <strong>⚠ STEP 1: Create these 5 databases in Hostinger hPanel first:</strong>
            <div class="db-list">
                <div>• u294365219_soc1 → System DB</div>
                <div>• u294365219_soc2 → Machine 1</div>
                <div>• u294365219_soc3 → Machine 2</div>
                <div>• u294365219_soc4 → Machine 3</div>
                <div>• u294365219_soc5 → Machine 4</div>
            </div>
            <strong>Create database user:</strong> u294365219_socuser<br>
            <strong>Grant ALL PRIVILEGES</strong> to this user for all 5 databases
        </div>

        <form method="POST">
            <label>🔒 Installer Password:</label>
            <input type="password" name="password" placeholder="Enter installer password" required>
            <div class="note">Default password: <code>SOC@Install2026</code></div>

            <label>👤 Database Username (from hPanel):</label>
            <input type="text" name="db_user" placeholder="u294365219_socuser" value="u294365219_socuser" required>

            <label>🔑 Database Password (from hPanel):</label>
            <input type="password" name="db_pass" placeholder="Enter the password you set in hPanel" required>

            <div class="note">
                <strong>💡 Optional - MySQL Admin Access:</strong><br>
                Only fill these if you have root/admin MySQL access (rare on shared hosting)
            </div>

            <label>MySQL Admin Username (optional):</label>
            <input type="text" name="admin_user" placeholder="root">

            <label>MySQL Admin Password (optional):</label>
            <input type="password" name="admin_pass" placeholder="Admin password">

            <button type="submit">🚀 Install & Configure Databases</button>
        </form>

        <div class="note" style="margin-top: 30px;">
            <strong>📋 What this installer does:</strong><br>
            ✓ Imports all SQL table schemas<br>
            ✓ Sets up admin user account<br>
            ✓ Configures threat logs system<br>
            ✓ Prepares 4 machine databases<br>
            ✓ Updates configuration files<br>
            <br>
            <strong style="color: #dc3545;">⚠ Delete this file after installation!</strong>
        </div>
    </div>
</body>
</html>
<?php
}
?>

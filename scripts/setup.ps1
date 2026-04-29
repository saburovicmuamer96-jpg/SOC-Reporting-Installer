# ============================================================
#  SOC Reporting System - Automated Setup Script
#  Downloads XAMPP, configures Apache/PHP/MariaDB, deploys app.
# ============================================================

param(
    [string]$InstallerDir = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = "Stop"

# --- Configuration ---
$XamppVersion = "8.2.12"
$XamppUrl = "https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/$XamppVersion/xampp-portable-windows-x64-$XamppVersion-0-VS16.zip/download"
$XamppDir = "C:\xampp"
$HtdocsDir = "$XamppDir\htdocs"
$AppDir = "$HtdocsDir\SOC-Reporting"
$MySQLBin = "$XamppDir\mysql\bin"
$DBUser = "soc_admin"
$DBPass = "SOC_Local_2024!"
$AdminPass = 'Admin@SOC2024'
$DownloadDir = "$InstallerDir\downloads"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "  [*] $Message" -ForegroundColor Cyan
}

function Write-OK {
    param([string]$Message)
    Write-Host "  [OK] $Message" -ForegroundColor Green
}

function Write-Err {
    param([string]$Message)
    Write-Host "  [X] $Message" -ForegroundColor Red
}

function Write-Warn {
    param([string]$Message)
    Write-Host "  [!] $Message" -ForegroundColor Yellow
}

# ============================================================
Write-Host ""
Write-Host "  ================================================" -ForegroundColor Cyan
Write-Host "   SOC Reporting System - Automated Setup" -ForegroundColor Cyan
Write-Host "   Version 1.0.0" -ForegroundColor Cyan
Write-Host "  ================================================" -ForegroundColor Cyan
Write-Host ""

# --- Step 1: Download XAMPP ---
Write-Step "Step 1/7: Checking XAMPP installation..."

if (Test-Path "$XamppDir\xampp-control.exe") {
    Write-OK "XAMPP already installed at $XamppDir"
} else {
    Write-Step "Downloading XAMPP $XamppVersion (this may take a few minutes)..."

    if (-Not (Test-Path $DownloadDir)) {
        New-Item -ItemType Directory -Path $DownloadDir -Force | Out-Null
    }

    $XamppZip = "$DownloadDir\xampp.zip"

    if (-Not (Test-Path $XamppZip)) {
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

            # Try direct download
            Write-Host "      Downloading from SourceForge..." -ForegroundColor Gray
            $webClient = New-Object System.Net.WebClient
            $webClient.Headers.Add("User-Agent", "Mozilla/5.0")
            $webClient.DownloadFile($XamppUrl, $XamppZip)
            Write-OK "XAMPP downloaded successfully."
        } catch {
            Write-Err "Automatic download failed."
            Write-Host ""
            Write-Warn "Please download XAMPP manually:"
            Write-Host "      1. Go to: https://www.apachefriends.org/download.html" -ForegroundColor White
            Write-Host "      2. Download XAMPP for Windows (PHP 8.2.x)" -ForegroundColor White
            Write-Host "      3. Install it to C:\xampp" -ForegroundColor White
            Write-Host "      4. Run this script again." -ForegroundColor White
            Write-Host ""
            Write-Host "  Alternatively, place the XAMPP ZIP file at:" -ForegroundColor Yellow
            Write-Host "      $XamppZip" -ForegroundColor White
            Write-Host ""
            Read-Host "  Press Enter after installing XAMPP manually"

            if (-Not (Test-Path "$XamppDir\xampp-control.exe")) {
                Write-Err "XAMPP still not found. Aborting."
                exit 1
            }
        }
    }

    if ((Test-Path $XamppZip) -And (-Not (Test-Path "$XamppDir\xampp-control.exe"))) {
        Write-Step "Extracting XAMPP to C:\..."
        try {
            Expand-Archive -Path $XamppZip -DestinationPath "C:\" -Force
            Write-OK "XAMPP extracted to $XamppDir"
        } catch {
            Write-Err "Failed to extract XAMPP. Please extract manually to C:\xampp"
            exit 1
        }
    }
}

# --- Step 2: Configure PHP ---
Write-Step "Step 2/7: Configuring PHP..."

$PhpIni = "$XamppDir\php\php.ini"
if (Test-Path $PhpIni) {
    $phpContent = Get-Content $PhpIni -Raw

    # Enable required extensions
    $extensions = @("extension=pdo_mysql", "extension=mbstring", "extension=openssl", "extension=gd")
    foreach ($ext in $extensions) {
        $disabled = ";$ext"
        if ($phpContent -match [regex]::Escape($disabled)) {
            $phpContent = $phpContent -replace [regex]::Escape($disabled), $ext
        }
    }

    # Set timezone
    $phpContent = $phpContent -replace ';?\s*date\.timezone\s*=.*', 'date.timezone = Europe/Vienna'

    Set-Content -Path $PhpIni -Value $phpContent
    Write-OK "PHP configured (extensions enabled, timezone set)."
} else {
    Write-Warn "php.ini not found, skipping PHP configuration."
}

# --- Step 3: Start MariaDB ---
Write-Step "Step 3/7: Starting MariaDB..."

# Check if MySQL is already running
$mysqlProc = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
if ($mysqlProc) {
    Write-OK "MariaDB is already running."
} else {
    if (Test-Path "$XamppDir\mysql_start.bat") {
        Start-Process -FilePath "$XamppDir\mysql_start.bat" -WindowStyle Hidden
        Start-Sleep -Seconds 5
        Write-OK "MariaDB started."
    } else {
        # Try direct mysqld start
        if (Test-Path "$MySQLBin\mysqld.exe") {
            Start-Process -FilePath "$MySQLBin\mysqld.exe" -ArgumentList "--defaults-file=`"$XamppDir\mysql\bin\my.ini`"" -WindowStyle Hidden
            Start-Sleep -Seconds 5
            Write-OK "MariaDB started directly."
        } else {
            Write-Err "Cannot find MariaDB. Please start it manually via XAMPP Control Panel."
        }
    }
}

# --- Step 4: Create database user and import schemas ---
Write-Step "Step 4/7: Setting up databases..."

$MySQLExe = "$MySQLBin\mysql.exe"
if (-Not (Test-Path $MySQLExe)) {
    Write-Err "mysql.exe not found at $MySQLExe"
    exit 1
}

# Create database user
$createUserSQL = @"
CREATE USER IF NOT EXISTS '$DBUser'@'localhost' IDENTIFIED BY '$DBPass';
GRANT ALL PRIVILEGES ON soc_system.* TO '$DBUser'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_1.* TO '$DBUser'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_2.* TO '$DBUser'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_3.* TO '$DBUser'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_4.* TO '$DBUser'@'localhost';
FLUSH PRIVILEGES;
"@

$createUserFile = "$DownloadDir\create_user.sql"
Set-Content -Path $createUserFile -Value $createUserSQL

try {
    & $MySQLExe -u root -e "SELECT 1" 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Warn "Cannot connect to MariaDB as root. Trying without password..."
    }

    & $MySQLExe -u root < $createUserFile 2>$null
    Write-OK "Database user '$DBUser' created."
} catch {
    Write-Warn "Could not create database user automatically."
    Write-Host "      You may need to create it manually in phpMyAdmin." -ForegroundColor Gray
}

# Import system schema
$SystemSQL = "$InstallerDir\SOC-Reporting\sql\system.sql"
if (Test-Path $SystemSQL) {
    try {
        & $MySQLExe -u root < $SystemSQL 2>$null
        Write-OK "System database (soc_system) imported."
    } catch {
        Write-Warn "Failed to import system.sql - you can do this via setup.php later."
    }
} else {
    Write-Warn "system.sql not found in installer package."
}

# Import machine schemas
$MachineTemplateSQL = "$InstallerDir\SOC-Reporting\sql\machine_template.sql"
if (Test-Path $MachineTemplateSQL) {
    $template = Get-Content $MachineTemplateSQL -Raw
    for ($i = 1; $i -le 4; $i++) {
        $machineSql = $template -replace '\{N\}', $i
        $machineFile = "$DownloadDir\machine_$i.sql"
        Set-Content -Path $machineFile -Value $machineSql
        try {
            & $MySQLExe -u root < $machineFile 2>$null
            Write-OK "Machine database (soc_machine_$i) imported."
        } catch {
            Write-Warn "Failed to import soc_machine_$i - you can use setup.php later."
        }
    }
} else {
    Write-Warn "machine_template.sql not found."
}

# Add deleted_reports tables to each machine DB
$deletedReportsSQL = @"
CREATE TABLE IF NOT EXISTS deleted_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(30) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL,
    machine_slot TINYINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'submitted',
    notes TEXT NULL,
    incident_time DATETIME NULL,
    reaction_time_seconds INT NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_by VARCHAR(50) NOT NULL,
    INDEX idx_event_id (event_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deleted_report_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deleted_report_id BIGINT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    field_value TEXT NULL,
    FOREIGN KEY (deleted_report_id) REFERENCES deleted_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;
"@

for ($i = 1; $i -le 4; $i++) {
    $delFile = "$DownloadDir\deleted_$i.sql"
    Set-Content -Path $delFile -Value "USE soc_machine_$i;`n$deletedReportsSQL"
    try {
        & $MySQLExe -u root < $delFile 2>$null
    } catch {}
}
Write-OK "Deleted reports archive tables created."

# --- Step 5: Deploy application files ---
Write-Step "Step 5/7: Deploying SOC Reporting application..."

$SourceApp = "$InstallerDir\SOC-Reporting"
if (Test-Path $SourceApp) {
    if (Test-Path $AppDir) {
        Write-Warn "Existing installation found at $AppDir - creating backup..."
        $backupDir = "${AppDir}_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        Rename-Item -Path $AppDir -NewName $backupDir
        Write-OK "Backup created at $backupDir"
    }

    Copy-Item -Path $SourceApp -Destination $AppDir -Recurse -Force
    Write-OK "Application deployed to $AppDir"
} else {
    Write-Err "SOC-Reporting source folder not found in installer package!"
    Write-Host "      Expected at: $SourceApp" -ForegroundColor Gray
    exit 1
}

# --- Step 6: Start Apache ---
Write-Step "Step 6/7: Starting Apache..."

$apacheProc = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
if ($apacheProc) {
    Write-OK "Apache is already running."
} else {
    if (Test-Path "$XamppDir\apache_start.bat") {
        Start-Process -FilePath "$XamppDir\apache_start.bat" -WindowStyle Hidden
        Start-Sleep -Seconds 3
        Write-OK "Apache started."
    } else {
        Write-Warn "Cannot start Apache automatically. Start it via XAMPP Control Panel."
    }
}

# --- Step 7: Final verification ---
Write-Step "Step 7/7: Verifying installation..."

$checks = @(
    @{ Name = "XAMPP installed"; Path = "$XamppDir\xampp-control.exe" },
    @{ Name = "PHP available"; Path = "$XamppDir\php\php.exe" },
    @{ Name = "MariaDB available"; Path = "$MySQLBin\mysql.exe" },
    @{ Name = "Application deployed"; Path = "$AppDir\index.php" },
    @{ Name = "Configuration files"; Path = "$AppDir\config\app.php" },
    @{ Name = "SQL schemas"; Path = "$AppDir\sql\system.sql" },
    @{ Name = "CSS theme"; Path = "$AppDir\assets\css\style.css" }
)

$allOK = $true
foreach ($check in $checks) {
    if (Test-Path $check.Path) {
        Write-OK "$($check.Name)"
    } else {
        Write-Err "$($check.Name) - NOT FOUND"
        $allOK = $false
    }
}

# --- Complete ---
Write-Host ""
Write-Host "  ================================================" -ForegroundColor Green
if ($allOK) {
    Write-Host "   INSTALLATION COMPLETE!" -ForegroundColor Green
} else {
    Write-Host "   INSTALLATION COMPLETED WITH WARNINGS" -ForegroundColor Yellow
}
Write-Host "  ================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Next steps:" -ForegroundColor White
Write-Host "  1. Open XAMPP Control Panel and ensure Apache + MySQL are running" -ForegroundColor Gray
Write-Host "  2. Open browser: http://localhost/SOC-Reporting/setup.php" -ForegroundColor Gray
Write-Host "  3. Verify all databases are created" -ForegroundColor Gray
Write-Host "  4. Login with: admin / $AdminPass" -ForegroundColor Gray
Write-Host "  5. CHANGE the admin password immediately!" -ForegroundColor Yellow
Write-Host "  6. DELETE setup.php after verification!" -ForegroundColor Yellow
Write-Host ""
Write-Host "  Application URL: http://localhost/SOC-Reporting/" -ForegroundColor Cyan
Write-Host ""

# Try to open the setup page
$openBrowser = Read-Host "  Open setup page in browser now? (Y/n)"
if ($openBrowser -ne 'n' -and $openBrowser -ne 'N') {
    Start-Process "http://localhost/SOC-Reporting/setup.php"
}

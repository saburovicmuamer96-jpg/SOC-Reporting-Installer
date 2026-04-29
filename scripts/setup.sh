#!/usr/bin/env bash
# ============================================================
#  SOC Reporting System - Linux/macOS Automated Setup Script
#  Installs PHP, MariaDB, Apache/Nginx, deploys the application.
# ============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALLER_DIR="$(dirname "$SCRIPT_DIR")"
APP_SOURCE="$INSTALLER_DIR/SOC-Reporting"

DB_USER="soc_admin"
DB_PASS="SOC_Local_2024!"
ADMIN_PASS="Admin@SOC2024"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
GRAY='\033[0;37m'
NC='\033[0m'

step()  { echo -e "\n  ${CYAN}[*] $1${NC}"; }
ok()    { echo -e "  ${GREEN}[OK] $1${NC}"; }
err()   { echo -e "  ${RED}[X] $1${NC}"; }
warn()  { echo -e "  ${YELLOW}[!] $1${NC}"; }

# Detect OS
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command -v apt-get &>/dev/null; then
            OS="debian"
        elif command -v dnf &>/dev/null; then
            OS="fedora"
        elif command -v yum &>/dev/null; then
            OS="centos"
        elif command -v pacman &>/dev/null; then
            OS="arch"
        else
            OS="linux"
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macos"
    else
        OS="unknown"
    fi
}

# ============================================================
echo ""
echo -e "  ${CYAN}================================================${NC}"
echo -e "  ${CYAN} SOC Reporting System - Linux/macOS Setup${NC}"
echo -e "  ${CYAN} Version 1.0.0${NC}"
echo -e "  ${CYAN}================================================${NC}"
echo ""

# Check root/sudo
if [[ "$EUID" -ne 0 ]] && [[ "$OSTYPE" == "linux-gnu"* ]]; then
    err "This script requires root privileges on Linux."
    echo -e "  ${GRAY}Run with: sudo bash $0${NC}"
    exit 1
fi

detect_os
step "Detected OS: $OS"

# --- Step 1: Install dependencies ---
step "Step 1/7: Installing dependencies..."

case "$OS" in
    debian)
        apt-get update -qq
        apt-get install -y -qq apache2 mariadb-server mariadb-client \
            php php-mysql php-mbstring php-gd php-xml php-curl \
            libapache2-mod-php unzip curl 2>/dev/null
        ok "Packages installed (apt)."
        ;;
    fedora)
        dnf install -y -q httpd mariadb-server mariadb \
            php php-mysqlnd php-mbstring php-gd php-xml php-curl \
            unzip curl 2>/dev/null
        ok "Packages installed (dnf)."
        ;;
    centos)
        yum install -y -q httpd mariadb-server mariadb \
            php php-mysqlnd php-mbstring php-gd php-xml php-curl \
            unzip curl 2>/dev/null
        ok "Packages installed (yum)."
        ;;
    arch)
        pacman -Sy --noconfirm apache mariadb php php-apache php-gd \
            unzip curl 2>/dev/null
        ok "Packages installed (pacman)."
        ;;
    macos)
        if command -v brew &>/dev/null; then
            brew install php mariadb 2>/dev/null || true
            ok "Packages installed (Homebrew)."
        else
            warn "Homebrew not found. Install from https://brew.sh"
            warn "Then run: brew install php mariadb"
        fi
        ;;
    *)
        warn "Could not detect package manager."
        warn "Please install manually: Apache/Nginx, PHP 8.x, MariaDB/MySQL"
        warn "Required PHP extensions: pdo_mysql, mbstring, gd, xml, curl"
        ;;
esac

# --- Step 2: Configure PHP ---
step "Step 2/7: Checking PHP configuration..."

if command -v php &>/dev/null; then
    PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
    ok "PHP $PHP_VERSION found."

    # Check required extensions
    REQUIRED_EXTS=("pdo_mysql" "mbstring")
    for ext in "${REQUIRED_EXTS[@]}"; do
        if php -m 2>/dev/null | grep -qi "$ext"; then
            ok "Extension: $ext"
        else
            warn "Extension $ext not loaded - check your php.ini"
        fi
    done

    # Set timezone if not set
    PHP_INI=$(php -r 'echo php_ini_loaded_file();' 2>/dev/null)
    if [[ -n "$PHP_INI" && -f "$PHP_INI" ]]; then
        if ! grep -q "^date.timezone" "$PHP_INI" 2>/dev/null; then
            echo 'date.timezone = Europe/Vienna' >> "$PHP_INI"
            ok "Timezone set in $PHP_INI"
        fi
    fi
else
    err "PHP not found. Please install PHP 8.x and try again."
    exit 1
fi

# --- Step 3: Start MariaDB ---
step "Step 3/7: Starting MariaDB..."

case "$OS" in
    debian|fedora|centos)
        if [[ "$OS" == "debian" ]]; then
            MYSQL_SERVICE="mariadb"
        else
            MYSQL_SERVICE="mariadb"
        fi
        systemctl enable "$MYSQL_SERVICE" 2>/dev/null || true
        systemctl start "$MYSQL_SERVICE" 2>/dev/null || true
        ok "MariaDB service started."
        ;;
    arch)
        # Arch needs initialization first
        if [[ ! -d /var/lib/mysql/mysql ]]; then
            mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql 2>/dev/null
        fi
        systemctl enable mariadb 2>/dev/null || true
        systemctl start mariadb 2>/dev/null || true
        ok "MariaDB service started."
        ;;
    macos)
        if command -v brew &>/dev/null; then
            brew services start mariadb 2>/dev/null || true
            ok "MariaDB started via Homebrew."
        else
            warn "Start MariaDB manually: mysql.server start"
        fi
        ;;
esac

# Wait for MariaDB to be ready
sleep 2

# --- Step 4: Create databases and user ---
step "Step 4/7: Setting up databases..."

MYSQL_CMD=""
if command -v mariadb &>/dev/null; then
    MYSQL_CMD="mariadb"
elif command -v mysql &>/dev/null; then
    MYSQL_CMD="mysql"
else
    err "MySQL/MariaDB client not found."
    exit 1
fi

# Try connecting as root (no password first, then socket auth)
MYSQL_ROOT=""
if $MYSQL_CMD -u root -e "SELECT 1" &>/dev/null; then
    MYSQL_ROOT="$MYSQL_CMD -u root"
elif sudo $MYSQL_CMD -u root -e "SELECT 1" &>/dev/null; then
    MYSQL_ROOT="sudo $MYSQL_CMD -u root"
else
    warn "Cannot connect to MariaDB as root."
    warn "You may need to run: sudo mysql_secure_installation"
    warn "Or create the databases manually."
    MYSQL_ROOT=""
fi

if [[ -n "$MYSQL_ROOT" ]]; then
    # Create user and grant privileges
    $MYSQL_ROOT <<EOSQL
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON soc_system.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_1.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_2.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_3.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_4.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOSQL
    ok "Database user '$DB_USER' created."

    # Import system schema
    if [[ -f "$APP_SOURCE/sql/system.sql" ]]; then
        $MYSQL_ROOT < "$APP_SOURCE/sql/system.sql" 2>/dev/null
        ok "System database (soc_system) imported."
    fi

    # Import threat_logs schema
    if [[ -f "$APP_SOURCE/sql/threat_logs.sql" ]]; then
        $MYSQL_ROOT < "$APP_SOURCE/sql/threat_logs.sql" 2>/dev/null
        ok "Threat logs table imported."
    fi

    # Import machine databases from template
    if [[ -f "$APP_SOURCE/sql/machine_template.sql" ]]; then
        TEMPLATE=$(cat "$APP_SOURCE/sql/machine_template.sql")
        for i in 1 2 3 4; do
            echo "${TEMPLATE//\{N\}/$i}" | $MYSQL_ROOT 2>/dev/null
            ok "Machine database (soc_machine_$i) imported."
        done
    fi

    # Import deleted reports migration for each machine
    if [[ -f "$APP_SOURCE/sql/migration_add_deleted_reports.sql" ]]; then
        MIGRATION=$(cat "$APP_SOURCE/sql/migration_add_deleted_reports.sql")
        for i in 1 2 3 4; do
            echo "USE soc_machine_$i; $MIGRATION" | $MYSQL_ROOT 2>/dev/null || true
        done
        ok "Deleted reports archive tables created."
    fi
fi

# --- Step 5: Deploy application ---
step "Step 5/7: Deploying application..."

# Determine web root
if [[ "$OS" == "macos" ]]; then
    WEB_ROOT="/Library/WebServer/Documents"
    [[ -d "/opt/homebrew/var/www" ]] && WEB_ROOT="/opt/homebrew/var/www"
    [[ -d "/usr/local/var/www" ]] && WEB_ROOT="/usr/local/var/www"
elif [[ "$OS" == "debian" ]]; then
    WEB_ROOT="/var/www/html"
elif [[ "$OS" == "fedora" || "$OS" == "centos" ]]; then
    WEB_ROOT="/var/www/html"
elif [[ "$OS" == "arch" ]]; then
    WEB_ROOT="/srv/http"
else
    WEB_ROOT="/var/www/html"
fi

APP_DIR="$WEB_ROOT/SOC-Reporting"

if [[ ! -d "$APP_SOURCE" ]]; then
    err "SOC-Reporting source folder not found at: $APP_SOURCE"
    exit 1
fi

# Backup existing installation
if [[ -d "$APP_DIR" ]]; then
    BACKUP_DIR="${APP_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
    warn "Existing installation found - backing up to $BACKUP_DIR"
    mv "$APP_DIR" "$BACKUP_DIR"
fi

# Copy application files
cp -r "$APP_SOURCE" "$APP_DIR"
ok "Application deployed to $APP_DIR"

# Create database config from example if it doesn't exist
if [[ ! -f "$APP_DIR/config/database.php" && -f "$APP_DIR/config/database.php.example" ]]; then
    sed -e "s/your_db_user/$DB_USER/" \
        -e "s/your_db_password/$DB_PASS/" \
        "$APP_DIR/config/database.php.example" > "$APP_DIR/config/database.php"
    ok "Database config created from template."
fi

# Set permissions
if [[ "$OS" != "macos" ]]; then
    WEB_USER="www-data"
    [[ "$OS" == "fedora" || "$OS" == "centos" ]] && WEB_USER="apache"
    [[ "$OS" == "arch" ]] && WEB_USER="http"

    chown -R "$WEB_USER:$WEB_USER" "$APP_DIR" 2>/dev/null || true
    chmod -R 755 "$APP_DIR" 2>/dev/null || true
    chmod -R 775 "$APP_DIR/cache" "$APP_DIR/logs" 2>/dev/null || true
    ok "File permissions set (owner: $WEB_USER)."
else
    chmod -R 755 "$APP_DIR" 2>/dev/null || true
    chmod -R 777 "$APP_DIR/cache" "$APP_DIR/logs" 2>/dev/null || true
    ok "File permissions set."
fi

# --- Step 6: Configure and start web server ---
step "Step 6/7: Configuring web server..."

case "$OS" in
    debian)
        # Enable mod_rewrite
        a2enmod rewrite 2>/dev/null || true
        systemctl enable apache2 2>/dev/null || true
        systemctl restart apache2 2>/dev/null || true
        ok "Apache started and configured."
        ;;
    fedora|centos)
        systemctl enable httpd 2>/dev/null || true
        systemctl restart httpd 2>/dev/null || true
        # Open firewall if firewalld is active
        if systemctl is-active firewalld &>/dev/null; then
            firewall-cmd --permanent --add-service=http 2>/dev/null || true
            firewall-cmd --reload 2>/dev/null || true
            ok "Firewall opened for HTTP."
        fi
        ok "Apache (httpd) started and configured."
        ;;
    arch)
        systemctl enable httpd 2>/dev/null || true
        systemctl restart httpd 2>/dev/null || true
        ok "Apache started."
        ;;
    macos)
        if command -v apachectl &>/dev/null; then
            sudo apachectl restart 2>/dev/null || true
            ok "Apache restarted."
        else
            warn "Start Apache manually or use PHP built-in server."
            warn "Quick start: cd $APP_DIR && php -S localhost:8080"
        fi
        ;;
esac

# --- Step 7: Verification ---
step "Step 7/7: Verifying installation..."

ALL_OK=true

check_item() {
    if [[ -e "$2" ]] || command -v "$2" &>/dev/null; then
        ok "$1"
    else
        err "$1 - NOT FOUND"
        ALL_OK=false
    fi
}

check_item "PHP available" "php"
check_item "MariaDB client" "$MYSQL_CMD"
check_item "Application deployed" "$APP_DIR/index.php"
check_item "Configuration" "$APP_DIR/config/app.php"
check_item "SQL schemas" "$APP_DIR/sql/system.sql"
check_item "CSS theme" "$APP_DIR/assets/css/style.css"

# --- Complete ---
echo ""
if [[ "$ALL_OK" == true ]]; then
    echo -e "  ${GREEN}================================================${NC}"
    echo -e "  ${GREEN} INSTALLATION COMPLETE!${NC}"
    echo -e "  ${GREEN}================================================${NC}"
else
    echo -e "  ${YELLOW}================================================${NC}"
    echo -e "  ${YELLOW} INSTALLATION COMPLETED WITH WARNINGS${NC}"
    echo -e "  ${YELLOW}================================================${NC}"
fi

echo ""
echo -e "  ${NC}Next steps:${NC}"
echo -e "  ${GRAY}1. Ensure Apache and MariaDB are running${NC}"
echo -e "  ${GRAY}2. Open browser: http://localhost/SOC-Reporting/setup.php${NC}"
echo -e "  ${GRAY}3. Verify all databases are created${NC}"
echo -e "  ${GRAY}4. Login with: admin / $ADMIN_PASS${NC}"
echo -e "  ${YELLOW}5. CHANGE the admin password immediately!${NC}"
echo -e "  ${YELLOW}6. DELETE setup.php after verification!${NC}"
echo ""
echo -e "  ${CYAN}Application URL: http://localhost/SOC-Reporting/${NC}"
echo ""
echo -e "  ${GRAY}Database credentials:${NC}"
echo -e "  ${GRAY}  User: $DB_USER${NC}"
echo -e "  ${GRAY}  Pass: $DB_PASS${NC}"
echo ""

# Offer to open browser
read -rp "  Open setup page in browser now? (Y/n) " OPEN_BROWSER
if [[ "$OPEN_BROWSER" != "n" && "$OPEN_BROWSER" != "N" ]]; then
    if command -v xdg-open &>/dev/null; then
        xdg-open "http://localhost/SOC-Reporting/setup.php" 2>/dev/null &
    elif command -v open &>/dev/null; then
        open "http://localhost/SOC-Reporting/setup.php" 2>/dev/null &
    else
        echo -e "  ${GRAY}Open manually: http://localhost/SOC-Reporting/setup.php${NC}"
    fi
fi

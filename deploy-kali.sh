#!/usr/bin/env bash
# ============================================================
#  SOC Reporting System — One-Click Kali Linux Deployment
#  Run: sudo bash deploy-kali.sh
# ============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_SOURCE="$SCRIPT_DIR/SOC-Reporting"
WEB_ROOT="/var/www/html"
APP_DIR="$WEB_ROOT/SOC-Reporting"

DB_NAME_SYSTEM="soc_system"
DB_USER="soc_admin"
DB_PASS="SOCKali2024!"
APP_ADMIN_PASS='Admin@SOC2024'

clear
echo -e "${CYAN}"
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║     SOC Reporting System — Kali Linux Deploy     ║"
echo "  ║                  Version 1.0.0                   ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo -e "${NC}"

# Root check
if [[ "$EUID" -ne 0 ]]; then
    echo -e "  ${RED}[X] Run as root: sudo bash $0${NC}"
    exit 1
fi

# Source check
if [[ ! -d "$APP_SOURCE" ]]; then
    echo -e "  ${RED}[X] SOC-Reporting/ folder not found next to this script.${NC}"
    echo -e "  ${RED}    Run this from the SOC-Reporting-Installer directory.${NC}"
    exit 1
fi

# ── Step 1: Install packages ─────────────────────────────
echo -e "\n  ${CYAN}[1/6]${NC} Installing Apache, PHP, MariaDB..."

export DEBIAN_FRONTEND=noninteractive

apt-get update -qq

apt-get install -y -qq \
    apache2 \
    mariadb-server \
    mariadb-client \
    php \
    php-mysql \
    php-mbstring \
    php-gd \
    php-xml \
    php-curl \
    php-intl \
    libapache2-mod-php \
    unzip \
    curl \
    > /dev/null 2>&1

echo -e "  ${GREEN}[OK]${NC} All packages installed."

# ── Step 2: Start services ───────────────────────────────
echo -e "\n  ${CYAN}[2/6]${NC} Starting services..."

systemctl enable apache2 mariadb > /dev/null 2>&1
systemctl start mariadb > /dev/null 2>&1

# Wait for MariaDB socket
for i in {1..10}; do
    if mariadb -u root -e "SELECT 1" &>/dev/null; then
        break
    fi
    sleep 1
done

a2enmod rewrite > /dev/null 2>&1
systemctl restart apache2 > /dev/null 2>&1

echo -e "  ${GREEN}[OK]${NC} Apache & MariaDB running."

# ── Step 3: Set up databases ─────────────────────────────
echo -e "\n  ${CYAN}[3/6]${NC} Creating databases & user..."

mariadb -u root <<EOSQL
-- Create user
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';

-- Grant privileges
GRANT ALL PRIVILEGES ON soc_system.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_1.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_2.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_3.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON soc_machine_4.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

echo -e "  ${GREEN}[OK]${NC} User '${DB_USER}' created."

# Import system schema
if [[ -f "$APP_SOURCE/sql/system.sql" ]]; then
    mariadb -u root < "$APP_SOURCE/sql/system.sql" 2>/dev/null
    echo -e "  ${GREEN}[OK]${NC} soc_system database imported."
fi

# Import threat_logs
if [[ -f "$APP_SOURCE/sql/threat_logs.sql" ]]; then
    mariadb -u root < "$APP_SOURCE/sql/threat_logs.sql" 2>/dev/null
    echo -e "  ${GREEN}[OK]${NC} threat_logs table imported."
fi

# Import machine databases from template
if [[ -f "$APP_SOURCE/sql/machine_template.sql" ]]; then
    TEMPLATE=$(cat "$APP_SOURCE/sql/machine_template.sql")
    for i in 1 2 3 4; do
        echo "${TEMPLATE//\{N\}/$i}" | mariadb -u root 2>/dev/null
        echo -e "  ${GREEN}[OK]${NC} soc_machine_${i} database imported."
    done
fi

# Import deleted reports migration
if [[ -f "$APP_SOURCE/sql/migration_add_deleted_reports.sql" ]]; then
    MIGRATION=$(cat "$APP_SOURCE/sql/migration_add_deleted_reports.sql")
    for i in 1 2 3 4; do
        echo "USE soc_machine_${i}; ${MIGRATION}" | mariadb -u root 2>/dev/null || true
    done
    echo -e "  ${GREEN}[OK]${NC} Deleted reports tables created."
fi

# ── Step 4: Deploy application ───────────────────────────
echo -e "\n  ${CYAN}[4/6]${NC} Deploying application..."

# Backup if exists
if [[ -d "$APP_DIR" ]]; then
    BACKUP="${APP_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
    mv "$APP_DIR" "$BACKUP"
    echo -e "  ${YELLOW}[!]${NC} Existing install backed up to ${BACKUP}"
fi

cp -r "$APP_SOURCE" "$APP_DIR"

# Create database.php from template
cat > "$APP_DIR/config/database.php" <<'DBEOF'
<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'DB_USER_PLACEHOLDER');
define('DB_PASS', 'DB_PASS_PLACEHOLDER');
define('DB_CHARSET', 'utf8mb4');

define('DB_SYSTEM', 'soc_system');
define('DB_MACHINE_PREFIX', 'soc_machine_');

define('DB_MACHINE_1', DB_MACHINE_PREFIX . '1');
define('DB_MACHINE_2', DB_MACHINE_PREFIX . '2');
define('DB_MACHINE_3', DB_MACHINE_PREFIX . '3');
define('DB_MACHINE_4', DB_MACHINE_PREFIX . '4');

define('DB_OPTIONS', serialize([
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    (defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? Pdo\Mysql::ATTR_INIT_COMMAND : 1002) => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]));
DBEOF

# Inject actual credentials
sed -i "s/DB_USER_PLACEHOLDER/${DB_USER}/" "$APP_DIR/config/database.php"
sed -i "s/DB_PASS_PLACEHOLDER/${DB_PASS}/" "$APP_DIR/config/database.php"

echo -e "  ${GREEN}[OK]${NC} Application deployed to ${APP_DIR}"

# ── Step 5: Set permissions ──────────────────────────────
echo -e "\n  ${CYAN}[5/6]${NC} Setting permissions..."

chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/cache" "$APP_DIR/logs" 2>/dev/null || true

echo -e "  ${GREEN}[OK]${NC} Permissions set."

# ── Step 6: Configure PHP timezone ───────────────────────
echo -e "\n  ${CYAN}[6/6]${NC} Configuring PHP..."

PHP_INI=$(php -r 'echo php_ini_loaded_file();' 2>/dev/null)
if [[ -n "$PHP_INI" && -f "$PHP_INI" ]]; then
    if ! grep -q "^date.timezone" "$PHP_INI" 2>/dev/null; then
        echo 'date.timezone = Europe/Vienna' >> "$PHP_INI"
    fi
fi

systemctl restart apache2 > /dev/null 2>&1

echo -e "  ${GREEN}[OK]${NC} PHP configured."

# ── Verify ───────────────────────────────────────────────
echo ""
echo -e "  ${CYAN}Verifying...${NC}"

ALL_OK=true
verify() {
    if [[ -e "$2" ]] || command -v "$2" &>/dev/null; then
        echo -e "  ${GREEN}[OK]${NC} $1"
    else
        echo -e "  ${RED}[X]${NC} $1"
        ALL_OK=false
    fi
}

verify "Apache running"       "$(systemctl is-active apache2 2>/dev/null && echo ok || echo '')"
verify "MariaDB running"      "$(systemctl is-active mariadb 2>/dev/null && echo ok || echo '')"
verify "PHP available"        "php"
verify "Application files"    "$APP_DIR/index.php"
verify "Database config"      "$APP_DIR/config/database.php"
verify "System DB exists"     "$(mariadb -u root -e 'USE soc_system' 2>/dev/null && echo ok || echo '')"

# ── Get IP ───────────────────────────────────────────────
KALI_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
[[ -z "$KALI_IP" ]] && KALI_IP="localhost"

# ── Done ─────────────────────────────────────────────────
echo ""
echo -e "  ${GREEN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "  ${GREEN}║          DEPLOYMENT COMPLETE!                    ║${NC}"
echo -e "  ${GREEN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${BOLD}Access:${NC}  http://${KALI_IP}/SOC-Reporting/"
echo -e "  ${BOLD}Login:${NC}   admin / ${APP_ADMIN_PASS}"
echo ""
echo -e "  ${BOLD}Database:${NC}"
echo -e "    User: ${DB_USER}"
echo -e "    Pass: ${DB_PASS}"
echo ""
echo -e "  ${YELLOW}[!] Change the admin password immediately!${NC}"
echo -e "  ${YELLOW}[!] Delete setup.php after verification!${NC}"
echo ""

# Try opening browser
if command -v xdg-open &>/dev/null; then
    su -c "xdg-open http://localhost/SOC-Reporting/ 2>/dev/null &" "${SUDO_USER:-root}" 2>/dev/null || true
elif command -v firefox &>/dev/null; then
    su -c "firefox http://localhost/SOC-Reporting/ 2>/dev/null &" "${SUDO_USER:-root}" 2>/dev/null || true
fi

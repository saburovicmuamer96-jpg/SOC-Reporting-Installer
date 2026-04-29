#!/usr/bin/env bash
# ============================================================
#  SOC Reporting System - Start Server (Linux/macOS)
# ============================================================

echo ""
echo "  ========================================"
echo "   SOC Reporting System - Starting..."
echo "  ========================================"
echo ""

# Detect init system and OS
start_service() {
    local service="$1"
    if command -v systemctl &>/dev/null; then
        sudo systemctl start "$service" 2>/dev/null && return 0
    elif command -v service &>/dev/null; then
        sudo service "$service" start 2>/dev/null && return 0
    fi
    return 1
}

# Start MariaDB
echo "  [*] Starting MariaDB..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    brew services start mariadb 2>/dev/null || mysql.server start 2>/dev/null
else
    start_service mariadb || start_service mysql || start_service mysqld
fi
echo "  [OK] MariaDB started."

# Start Apache
echo "  [*] Starting Apache..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    sudo apachectl start 2>/dev/null
else
    start_service apache2 || start_service httpd
fi
echo "  [OK] Apache started."

sleep 2

# Open browser
echo ""
echo "  [OK] Server is running!"
echo "  [OK] Access the system at: http://localhost/SOC-Reporting/"
echo ""

if command -v xdg-open &>/dev/null; then
    xdg-open "http://localhost/SOC-Reporting/" 2>/dev/null &
elif command -v open &>/dev/null; then
    open "http://localhost/SOC-Reporting/" 2>/dev/null &
fi

#!/usr/bin/env bash
# ============================================================
#  SOC Reporting System - Stop Server (Linux/macOS)
# ============================================================

echo ""
echo "  ========================================"
echo "   SOC Reporting System - Stopping..."
echo "  ========================================"
echo ""

stop_service() {
    local service="$1"
    if command -v systemctl &>/dev/null; then
        sudo systemctl stop "$service" 2>/dev/null && return 0
    elif command -v service &>/dev/null; then
        sudo service "$service" stop 2>/dev/null && return 0
    fi
    return 1
}

# Stop Apache
echo "  [*] Stopping Apache..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    sudo apachectl stop 2>/dev/null
else
    stop_service apache2 || stop_service httpd
fi
echo "  [OK] Apache stopped."

# Stop MariaDB
echo "  [*] Stopping MariaDB..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    brew services stop mariadb 2>/dev/null || mysql.server stop 2>/dev/null
else
    stop_service mariadb || stop_service mysql || stop_service mysqld
fi
echo "  [OK] MariaDB stopped."

echo ""
echo "  [OK] All services stopped."
echo ""

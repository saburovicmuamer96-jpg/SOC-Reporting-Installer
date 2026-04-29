#!/usr/bin/env bash
# ============================================================
#  SOC Reporting System - Linux/macOS Installer
#  Run this script to start the installation.
# ============================================================

echo ""
echo "  ========================================"
echo "   SOC Reporting System - Installer"
echo "   Version 1.0.0"
echo "  ========================================"
echo ""

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SETUP_SCRIPT="$SCRIPT_DIR/scripts/setup.sh"

if [[ ! -f "$SETUP_SCRIPT" ]]; then
    echo "  [X] Setup script not found at: $SETUP_SCRIPT"
    exit 1
fi

chmod +x "$SETUP_SCRIPT"

if [[ "$EUID" -ne 0 ]] && [[ "$OSTYPE" == "linux-gnu"* ]]; then
    echo "  [!] Root privileges required. Re-running with sudo..."
    echo ""
    exec sudo bash "$SETUP_SCRIPT"
else
    exec bash "$SETUP_SCRIPT"
fi

================================================================
  SOC REPORTING SYSTEM - INSTALLER PACKAGE
  Version 1.0.0
================================================================

QUICK START (Windows):
  1. Right-click INSTALL.bat -> "Run as administrator"
  2. Wait for the automated setup to complete
  3. Open http://localhost/SOC-Reporting/ in your browser
  4. Login: admin / Admin@SOC2024
  5. CHANGE THE PASSWORD IMMEDIATELY!

QUICK START (Linux/macOS):
  1. Open a terminal in this directory
  2. Run: sudo bash install.sh
  3. Wait for the automated setup to complete
  4. Open http://localhost/SOC-Reporting/ in your browser
  5. Login: admin / Admin@SOC2024
  6. CHANGE THE PASSWORD IMMEDIATELY!

CONTENTS:
  Windows:
    INSTALL.bat                            - Windows installer (run first!)
    START-SERVER.bat                       - Start Apache + MariaDB (Windows)
    STOP-SERVER.bat                        - Stop Apache + MariaDB (Windows)
    scripts/setup.ps1                      - PowerShell setup script

  Linux/macOS:
    install.sh                             - Linux/macOS installer (run first!)
    start-server.sh                        - Start Apache + MariaDB (Linux/macOS)
    stop-server.sh                         - Stop Apache + MariaDB (Linux/macOS)
    scripts/setup.sh                       - Bash setup script

  Shared:
    SOC-Reporting-Installation-Guide.html  - Full installation guide (printable)
    README.txt                             - This file
    SOC-Reporting/                         - Application source files

MANUAL INSTALLATION:
  Open SOC-Reporting-Installation-Guide.html in a browser for the full
  step-by-step guide. Click "Print / Save as PDF" to create a PDF copy.

REQUIREMENTS:
  Windows:
    - Windows 10/11 (64-bit) or Windows Server 2019+
    - Internet connection (for XAMPP download on first install)
    - Administrator privileges

  Linux:
    - Ubuntu/Debian, Fedora/RHEL, CentOS, or Arch Linux
    - Root/sudo access
    - Internet connection (for package installation)

  macOS:
    - macOS 12+ with Homebrew (https://brew.sh)

SUPPORTED LINUX DISTRIBUTIONS:
  The installer auto-detects and supports:
    - Ubuntu / Debian    (apt)
    - Fedora             (dnf)
    - CentOS / RHEL      (yum)
    - Arch Linux         (pacman)
    - macOS              (Homebrew)

DEFAULT CREDENTIALS:
  Application:  admin / Admin@SOC2024
  Database:     soc_admin / SOC_Local_2024!

AFTER INSTALLATION:
  - Change the admin password in Admin Panel > Users
  - Configure machine names in Admin Panel > Machines
  - Build report forms in Admin Panel > Form Builder
  - Delete setup.php from the server
  - Set up regular database backups

SUPPORT:
  See Troubleshooting section in the Installation Guide.
  GitHub: https://github.com/saburovicmuamer96-jpg/SOC-Reporting-Installer

================================================================

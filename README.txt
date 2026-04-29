================================================================
  SOC REPORTING SYSTEM - WINDOWS INSTALLER PACKAGE
  Version 1.0.0
================================================================

QUICK START:
  1. Right-click INSTALL.bat -> "Run as administrator"
  2. Wait for the automated setup to complete
  3. Open http://localhost/SOC-Reporting/ in your browser
  4. Login: admin / Admin@SOC2024
  5. CHANGE THE PASSWORD IMMEDIATELY!

CONTENTS:
  INSTALL.bat                          - Automated installer (run first!)
  START-SERVER.bat                     - Start Apache + MariaDB
  STOP-SERVER.bat                      - Stop Apache + MariaDB
  SOC-Reporting-Installation-Guide.html - Full installation guide (printable)
  README.txt                           - This file
  scripts/setup.ps1                    - PowerShell setup script
  SOC-Reporting/                       - Application source files

MANUAL INSTALLATION:
  Open SOC-Reporting-Installation-Guide.html in a browser for the full
  step-by-step guide. Click "Print / Save as PDF" to create a PDF copy.

REQUIREMENTS:
  - Windows 10/11 (64-bit) or Windows Server 2019+
  - Internet connection (for XAMPP download on first install)
  - Administrator privileges

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

================================================================

@echo off
REM ============================================================
REM  SOC Reporting System - Start Server
REM  Double-click to start Apache + MariaDB and open the app.
REM ============================================================

title SOC Reporting System - Server

set XAMPP_DIR=C:\xampp

if not exist "%XAMPP_DIR%\xampp-control.exe" (
    echo  [X] XAMPP not found at %XAMPP_DIR%
    echo  [X] Please run INSTALL.bat first.
    pause
    exit /b 1
)

echo.
echo  ========================================
echo   SOC Reporting System - Starting...
echo  ========================================
echo.

REM Start Apache
echo  [*] Starting Apache...
call "%XAMPP_DIR%\apache_start.bat" >nul 2>&1

REM Start MariaDB
echo  [*] Starting MariaDB...
call "%XAMPP_DIR%\mysql_start.bat" >nul 2>&1

REM Wait for services to start
echo  [*] Waiting for services...
timeout /t 3 /nobreak >nul

REM Open browser
echo  [*] Opening SOC Reporting System in browser...
start http://localhost/SOC-Reporting/

echo.
echo  [OK] Server is running!
echo  [OK] Access the system at: http://localhost/SOC-Reporting/
echo.
echo  Press any key to open XAMPP Control Panel...
pause >nul

start "" "%XAMPP_DIR%\xampp-control.exe"

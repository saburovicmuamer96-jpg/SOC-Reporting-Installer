@echo off
REM ============================================================
REM  SOC Reporting System - Stop Server
REM  Double-click to stop Apache + MariaDB.
REM ============================================================

title SOC Reporting System - Stop Server

set XAMPP_DIR=C:\xampp

echo.
echo  ========================================
echo   SOC Reporting System - Stopping...
echo  ========================================
echo.

echo  [*] Stopping Apache...
call "%XAMPP_DIR%\apache_stop.bat" >nul 2>&1

echo  [*] Stopping MariaDB...
call "%XAMPP_DIR%\mysql_stop.bat" >nul 2>&1

echo.
echo  [OK] All services stopped.
echo.
pause

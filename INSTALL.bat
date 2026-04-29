@echo off
REM ============================================================
REM  SOC Reporting System - Windows Installer
REM  Double-click this file to start the installation.
REM ============================================================

title SOC Reporting System - Installer
color 0B

echo.
echo  ========================================
echo   SOC Reporting System - Installer
echo   Version 1.0.0
echo  ========================================
echo.

REM Check for admin privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo  [!] This installer requires administrator privileges.
    echo  [!] Right-click INSTALL.bat and select "Run as administrator".
    echo.
    pause
    exit /b 1
)

REM Check if PowerShell is available
where powershell >nul 2>&1
if %errorlevel% neq 0 (
    echo  [X] PowerShell is required but not found.
    echo  [X] Please install PowerShell and try again.
    pause
    exit /b 1
)

echo  [*] Starting installation via PowerShell...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup.ps1" -InstallerDir "%~dp0"

echo.
echo  ========================================
echo   Installation process finished.
echo  ========================================
echo.
pause

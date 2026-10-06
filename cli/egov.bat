@echo off
setlocal enabledelayedexpansion
title E-Gov Diskominfo Bintan Environment Manager

:: Auto-detect root directory of egov-lamp
set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%.."
set "ROOT_DIR=%CD%"

:MENU
cls
echo =====================================================
echo    E-GOVERNMENT DISKOMINFO KABUPATEN BINTAN (Windows)
echo       Multi-PHP Local Development Environment
echo =====================================================
echo Direktori : %ROOT_DIR%
echo.
echo Pilih aksi yang ingin dilakukan:
echo   [1] Nyalakan PHP Tertentu (Pilih Versi: 7.4 - 8.3)
echo   [2] Nyalakan Default / Rekomendasi (PHP 7.4 + DB + PMA)
echo   [3] Nyalakan SEMUA Versi PHP Sekaligus
echo   [4] Matikan Semua Container egov-lamp
echo   [5] Cek Status Container Aktif
echo   [6] Pasang Shortcut CLI ke PowerShell (php74, composer74, artisan, dll)
echo   [0] Keluar
echo.
set "ACTION_CHOICE="
set /p "ACTION_CHOICE=Masukkan pilihan [0-6]: "

if "%ACTION_CHOICE%"=="1" goto CHOOSE_PHP
if "%ACTION_CHOICE%"=="2" goto START_DEFAULT
if "%ACTION_CHOICE%"=="3" goto START_ALL
if "%ACTION_CHOICE%"=="4" goto STOP_ALL
if "%ACTION_CHOICE%"=="5" goto STATUS
if "%ACTION_CHOICE%"=="6" goto SETUP_PS
if "%ACTION_CHOICE%"=="0" goto EXIT
goto INVALID

:CHOOSE_PHP
echo.
echo Pilih versi PHP yang ingin dinyalakan (bisa lebih dari satu, contoh: 1 atau 1 4):
echo   [1] PHP 7.4 (Port :8074)
echo   [2] PHP 8.0 (Port :8080)
echo   [3] PHP 8.1 (Port :8081)
echo   [4] PHP 8.2 (Port :8082)
echo   [5] PHP 8.3 (Port :8083)
echo.
set "PHP_CHOICE="
set /p "PHP_CHOICE=Pilih nomor versi [1-5]: "

if not defined PHP_CHOICE goto INVALID

set "TARGET_PHP="
for %%v in (%PHP_CHOICE%) do (
    if "%%v"=="1" set "TARGET_PHP=!TARGET_PHP! php74"
    if "%%v"=="7.4" set "TARGET_PHP=!TARGET_PHP! php74"
    if "%%v"=="74" set "TARGET_PHP=!TARGET_PHP! php74"
    if "%%v"=="2" set "TARGET_PHP=!TARGET_PHP! php80"
    if "%%v"=="8.0" set "TARGET_PHP=!TARGET_PHP! php80"
    if "%%v"=="8" set "TARGET_PHP=!TARGET_PHP! php80"
    if "%%v"=="80" set "TARGET_PHP=!TARGET_PHP! php80"
    if "%%v"=="3" set "TARGET_PHP=!TARGET_PHP! php81"
    if "%%v"=="8.1" set "TARGET_PHP=!TARGET_PHP! php81"
    if "%%v"=="81" set "TARGET_PHP=!TARGET_PHP! php81"
    if "%%v"=="4" set "TARGET_PHP=!TARGET_PHP! php82"
    if "%%v"=="8.2" set "TARGET_PHP=!TARGET_PHP! php82"
    if "%%v"=="82" set "TARGET_PHP=!TARGET_PHP! php82"
    if "%%v"=="5" set "TARGET_PHP=!TARGET_PHP! php83"
    if "%%v"=="8.3" set "TARGET_PHP=!TARGET_PHP! php83"
    if "%%v"=="83" set "TARGET_PHP=!TARGET_PHP! php83"
)

if not defined TARGET_PHP goto INVALID

echo.
echo Menyalakan database, phpmyadmin, redis, dan%TARGET_PHP%...
docker compose up -d database phpmyadmin redis%TARGET_PHP%
echo.
echo [OK] Container berhasil dinyalakan!
echo Dashboard Web : http://localhost:8083 (atau port PHP yang dipilih)
echo phpMyAdmin    : http://localhost:8888
echo MariaDB Port  : 3306 (user: root/docker, pass: tiger/docker)
echo.
pause
goto MENU

:START_DEFAULT
echo.
echo Menyalakan mode Default (PHP 7.4 + DB + phpMyAdmin + Redis)...
docker compose up -d database phpmyadmin redis php74
echo.
echo [OK] Berhasil dinyalakan!
echo Dashboard Web : http://localhost:8074
echo phpMyAdmin    : http://localhost:8888
echo.
pause
goto MENU

:START_ALL
echo.
echo Menyalakan SEMUA container (PHP 7.4 s/d 8.3)...
docker compose up -d
echo.
echo [OK] Semua container aktif!
echo Dashboard Web : http://localhost:8083
echo.
pause
goto MENU

:STOP_ALL
echo.
echo Mematikan semua container egov-lamp...
docker compose down
echo.
echo [OK] Container berhasil dimatikan.
echo.
pause
goto MENU

:STATUS
echo.
echo --- Status Container Aktif ---
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo.
pause
goto MENU

:SETUP_PS
echo.
powershell -ExecutionPolicy Bypass -NoProfile -File "%ROOT_DIR%\cli\install-shortcut.ps1"
echo.
pause
goto MENU

:INVALID
echo.
echo Pilihan tidak valid! Pastikan Anda memasukkan angka 0 sampai 6.
echo.
pause
goto MENU

:EXIT
exit /b 0

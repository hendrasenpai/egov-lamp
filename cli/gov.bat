@echo off
setlocal enabledelayedexpansion
title GOV-LAMP Environment Manager

:: Auto-detect root directory of gov-lamp
set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%.."
set "ROOT_DIR=%CD%"

:MENU
cls
echo =====================================================
echo          GOV-LAMP ENVIRONMENT MANAGER (Windows)
echo =====================================================
echo Direktori : %ROOT_DIR%
echo.
echo Pilih aksi yang ingin dilakukan:
echo   [1] Nyalakan PHP Tertentu (Pilih Versi: 7.4 - 8.3)
echo   [2] Nyalakan Default / Rekomendasi (PHP 7.4 + DB + PMA)
echo   [3] Nyalakan SEMUA Versi PHP Sekaligus
echo   [4] Matikan Semua Container gov-lamp
echo   [5] Cek Status Container Aktif
echo   [0] Keluar
echo.
set /p ACTION_CHOICE="Masukkan pilihan [0-5]: "

if "%ACTION_CHOICE%"=="1" goto CHOOSE_PHP
if "%ACTION_CHOICE%"=="2" goto START_DEFAULT
if "%ACTION_CHOICE%"=="3" goto START_ALL
if "%ACTION_CHOICE%"=="4" goto STOP_ALL
if "%ACTION_CHOICE%"=="5" goto STATUS
if "%ACTION_CHOICE%"=="0" goto EXIT
goto INVALID

:CHOOSE_PHP
echo.
echo Pilih versi PHP yang ingin dinyalakan:
echo   [1] PHP 7.4 (Port :8074)
echo   [2] PHP 8.0 (Port :8080)
echo   [3] PHP 8.1 (Port :8081)
echo   [4] PHP 8.2 (Port :8082)
echo   [5] PHP 8.3 (Port :8083)
echo.
set /p PHP_CHOICE="Pilih nomor versi [1-5]: "

set "TARGET_PHP="
if "%PHP_CHOICE%"=="1" set "TARGET_PHP=php74"
if "%PHP_CHOICE%"=="2" set "TARGET_PHP=php8"
if "%PHP_CHOICE%"=="3" set "TARGET_PHP=php81"
if "%PHP_CHOICE%"=="4" set "TARGET_PHP=php82"
if "%PHP_CHOICE%"=="5" set "TARGET_PHP=php83"

if "%TARGET_PHP%"=="" goto INVALID

echo.
echo Menyalakan database, phpmyadmin, redis, dan %TARGET_PHP%...
docker compose up -d database phpmyadmin redis %TARGET_PHP%
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
echo Mematikan semua container gov-lamp...
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

:INVALID
echo.
echo Pilihan tidak valid!
echo.
pause
goto MENU

:EXIT
exit /b 0

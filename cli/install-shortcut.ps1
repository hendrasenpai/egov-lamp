# ==============================================================================
# EGOV-LAMP - PowerShell Profile Shortcut Installer
# ==============================================================================
$ErrorActionPreference = 'Stop'

Write-Host "Memasang shortcut EGOV-LAMP ke profil PowerShell..." -ForegroundColor Cyan

# Deteksi root directory egov-lamp secara dinamis
$scriptDir = $PSScriptRoot
if (-not $scriptDir -and $MyInvocation.MyCommand.Path) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
$egovRoot = ""
if ($scriptDir) {
    $egovRoot = Split-Path -Parent $scriptDir
}
if (-not $egovRoot -or -not (Test-Path "$egovRoot\docker-compose.yml")) {
    $egovRoot = (Get-Location).Path
}

$helperScript = Join-Path $egovRoot "cli\docker-php-helpers.ps1"
if (-not (Test-Path $helperScript)) {
    Write-Host "[ERROR] File helper tidak ditemukan di: $helperScript" -ForegroundColor Red
    exit 1
}

$line = ". `"$helperScript`""

# Daftar lokasi profil yang mungkin (Windows PowerShell 5.1 & PowerShell 7, OneDrive & Local)
$profilePaths = @(
    $PROFILE,
    "$HOME\Documents\WindowsPowerShell\Microsoft.PowerShell_profile.ps1",
    "$HOME\Documents\PowerShell\Microsoft.PowerShell_profile.ps1"
) | Where-Object { $_ } | Select-Object -Unique

$success = $false

foreach ($p in $profilePaths) {
    try {
        $parentDir = Split-Path -Parent $p
        if (-not (Test-Path $parentDir)) {
            [System.IO.Directory]::CreateDirectory($parentDir) | Out-Null
        }
        if (-not (Test-Path $p)) {
            [System.IO.File]::WriteAllText($p, "# EGOV-LAMP PowerShell Profile`r`n")
        }
        $existing = Get-Content $p -ErrorAction SilentlyContinue | Out-String
        if ($existing -notmatch [regex]::Escape($line)) {
            Add-Content -Path $p -Value "`r`n$line"
        }
        $success = $true
    } catch {
        # Lanjutkan jika salah satu lokasi tidak dapat diakses
    }
}

if ($success) {
    Write-Host ""
    Write-Host "[OK] Shortcut helpers berhasil didaftarkan ke profil PowerShell!" -ForegroundColor Green
    Write-Host "Lokasi root eGov : $egovRoot" -ForegroundColor Yellow
    Write-Host "Silakan tutup dan buka kembali PowerShell Anda, atau ketik: . `$PROFILE" -ForegroundColor Cyan
} else {
    Write-Host "[ERROR] Gagal menambahkan shortcut ke profil PowerShell." -ForegroundColor Red
    exit 1
}

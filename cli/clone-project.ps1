# ==============================================================================
# EGOV-LAMP PROJECT CLONER (PowerShell)
# Otomatis clone repo dari https://github.com/tim-it-diskominfobintan
# ==============================================================================
param (
    [string]$RepoInput = "",
    [string]$PhpInput = ""
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RootDir = Split-Path -Parent $ScriptDir
if (-not (Test-Path "$RootDir\docker-compose.yml")) {
    $RootDir = Get-Location
}

$OrgBaseHttps = "https://github.com/tim-it-diskominfobintan"
$OrgBaseSsh = "git@github.com:tim-it-diskominfobintan"

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host "   📦 CLONE REPOSITORY DISKOMINFO BINTAN (Windows)   " -ForegroundColor Cyan
Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host "Organisasi Default : $OrgBaseHttps" -ForegroundColor Yellow
Write-Host "Direktori Tujuan   : $RootDir\www\" -ForegroundColor Yellow
Write-Host ""

# 1. Dapatkan nama / URL repo
if ([string]::IsNullOrWhiteSpace($RepoInput)) {
    Write-Host "Masukkan nama repository (contoh: web_bintan, b-smart) atau full URL:"
    $RepoInput = Read-Host "Nama / URL Repo"
}

if ([string]::IsNullOrWhiteSpace($RepoInput)) {
    Write-Host "Nama repository tidak boleh kosong!" -ForegroundColor Red
    exit 1
}

if ($RepoInput -match "^https?://" -or $RepoInput -match "^git@") {
    $CloneUrl = $RepoInput
    $RepoName = [System.IO.Path]::GetFileNameWithoutExtension($RepoInput)
} else {
    $RepoName = $RepoInput
    Write-Host ""
    Write-Host "Pilih metode clone untuk $RepoName :"
    Write-Host "  [1] HTTPS (Default: $OrgBaseHttps/$RepoName.git)"
    Write-Host "  [2] SSH   ($OrgBaseSsh/$RepoName.git)"
    $Proto = Read-Host "Pilihan [1/2] (default 1)"
    if ($Proto -eq "2") {
        $CloneUrl = "$OrgBaseSsh/$RepoName.git"
    } else {
        $CloneUrl = "$OrgBaseHttps/$RepoName.git"
    }
}

$TargetDir = "$RootDir\www\$RepoName"

# 2. Cek apakah folder sudah ada
if (Test-Path $TargetDir) {
    Write-Host ""
    Write-Host "Peringatan: Folder www\$RepoName sudah ada!" -ForegroundColor Yellow
    $Pull = Read-Host "Apakah ingin menjalankan 'git pull' untuk memperbarui? [Y/n]"
    if ($Pull -eq "" -or $Pull -match "^[Yy]") {
        Push-Location $TargetDir
        git pull
        Pop-Location
    }
} else {
    Write-Host ""
    Write-Host "Meng-clone $CloneUrl ke www\$RepoName..." -ForegroundColor Yellow
    git clone $CloneUrl $TargetDir
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Gagal melakukan clone repository!" -ForegroundColor Red
        Write-Host "Pastikan nama repository benar atau Anda memiliki izin akses ke tim-it-diskominfobintan." -ForegroundColor Yellow
        exit 1
    }
    Write-Host "✔ Berhasil di-clone ke www\$RepoName!" -ForegroundColor Green
}

# 3. Setup Versi PHP (.ws)
if ([string]::IsNullOrWhiteSpace($PhpInput)) {
    Write-Host ""
    Write-Host "Pilih versi PHP untuk project ini:" -ForegroundColor Cyan
    Write-Host "  [1] PHP 7.4 (Port :8074)"
    Write-Host "  [2] PHP 8.0 (Port :8080)"
    Write-Host "  [3] PHP 8.1 (Port :8081)"
    Write-Host "  [4] PHP 8.2 (Port :8082 - Rekomendasi Default)"
    Write-Host "  [5] PHP 8.3 (Port :8083)"
    $Choice = Read-Host "Pilihan versi [1-5] (default 4)"
} else {
    $Choice = $PhpInput
}

switch -Regex ($Choice) {
    "1|7\.4|74" { $PhpVer = "7.4"; $PhpContainer = "php74"; $Port = "8074" }
    "2|8\.0|80" { $PhpVer = "8.0"; $PhpContainer = "php80"; $Port = "8080" }
    "3|8\.1|81" { $PhpVer = "8.1"; $PhpContainer = "php81"; $Port = "8081" }
    "5|8\.3|83" { $PhpVer = "8.3"; $PhpContainer = "php83"; $Port = "8083" }
    default     { $PhpVer = "8.2"; $PhpContainer = "php82"; $Port = "8082" }
}

Set-Content -Path "$TargetDir\.ws" -Value $PhpVer -NoNewline
Write-Host "✔ Versi PHP project diset ke PHP $PhpVer (file .ws dibuat)." -ForegroundColor Green

# 4. Setup .env Laravel
if ((Test-Path "$TargetDir\.env.example") -and -not (Test-Path "$TargetDir\.env")) {
    Write-Host ""
    $EnvConfirm = Read-Host "Ditemukan .env.example. Buat file .env dengan konfigurasi database Docker? [Y/n]"
    if ($EnvConfirm -eq "" -or $EnvConfirm -match "^[Yy]") {
        Copy-Item "$TargetDir\.env.example" "$TargetDir\.env"
        (Get-Content "$TargetDir\.env") |
            ForEach-Object { $_ -replace "^DB_HOST=.*", "DB_HOST=database" } |
            ForEach-Object { $_ -replace "^DB_PORT=.*", "DB_PORT=3306" } |
            ForEach-Object { $_ -replace "^DB_USERNAME=.*", "DB_USERNAME=root" } |
            ForEach-Object { $_ -replace "^DB_PASSWORD=.*", "DB_PASSWORD=tiger" } |
            ForEach-Object { $_ -replace "^DB_DATABASE=.*", "DB_DATABASE=$RepoName" } |
            ForEach-Object { $_ -replace "^REDIS_HOST=.*", "REDIS_HOST=redis" } |
            Set-Content "$TargetDir\.env"
        Write-Host "✔ File .env dibuat otomatis (DB_HOST=database, user=root, pass=tiger)!" -ForegroundColor Green
    }
}

# 5. Composer Install jika diperlukan
if ((Test-Path "$TargetDir\composer.json") -and -not (Test-Path "$TargetDir\vendor")) {
    Write-Host ""
    $CompConfirm = Read-Host "Jalankan 'composer install' via Docker PHP $PhpVer? [Y/n]"
    if ($CompConfirm -eq "" -or $CompConfirm -match "^[Yy]") {
        Write-Host "Memastikan container egov-$PhpContainer aktif..." -ForegroundColor Yellow
        Push-Location $RootDir
        docker compose up -d database redis $PhpContainer
        Pop-Location

        Write-Host "Menjalankan composer install di dalam container..." -ForegroundColor Yellow
        docker exec -it -w "/var/www/html/$RepoName" "egov-$PhpContainer" composer install

        if ((Test-Path "$TargetDir\artisan") -and (Test-Path "$TargetDir\.env")) {
            $EnvContent = Get-Content "$TargetDir\.env" -Raw
            if ($EnvContent -notmatch "APP_KEY=base64:") {
                Write-Host "Menjalankan artisan key:generate..." -ForegroundColor Yellow
                docker exec -it -w "/var/www/html/$RepoName" "egov-$PhpContainer" php artisan key:generate
            }
        }
    }
}

# 6. Buka ke Editor
Write-Host ""
$OpenConfirm = Read-Host "Buka project di Antigravity IDE / VS Code sekarang? [Y/n]"
if ($OpenConfirm -eq "" -or $OpenConfirm -match "^[Yy]") {
    if (Get-Command antigravity-ide -ErrorAction SilentlyContinue) {
        Start-Process antigravity-ide -ArgumentList "`"$TargetDir`""
    } elseif (Get-Command antigravity -ErrorAction SilentlyContinue) {
        Start-Process antigravity -ArgumentList "`"$TargetDir`""
    } elseif (Get-Command code -ErrorAction SilentlyContinue) {
        Start-Process code -ArgumentList "`"$TargetDir`""
    }
}

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Green
Write-Host "   🎉 PROJECT BERHASIL DISIAPKAN!                    " -ForegroundColor Green
Write-Host "=====================================================" -ForegroundColor Green
Write-Host "Nama Project : $RepoName"
Write-Host "Lokasi Host  : $TargetDir" -ForegroundColor Yellow
Write-Host "Versi PHP    : PHP $PhpVer" -ForegroundColor Cyan
Write-Host "Akses Web    : http://localhost:$Port/$RepoName" -ForegroundColor Cyan
Write-Host "phpMyAdmin   : http://localhost:8888" -ForegroundColor Cyan
Write-Host "Database     : MariaDB (Host: database, DB: $RepoName, User: root, Pass: tiger)" -ForegroundColor Cyan
Write-Host ""

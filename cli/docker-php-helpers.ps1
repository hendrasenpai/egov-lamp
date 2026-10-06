# ==============================================================================
# EGOV-LAMP CLI HELPERS FOR WINDOWS POWERSHELL
# ==============================================================================
# Cara pemakaian di PowerShell:
# 1. Untuk sesi saat ini:
#    . C:\egov-lamp\cli\docker-php-helpers.ps1
#
# 2. Agar aktif permanen di setiap buka PowerShell:
#    if (!(Test-Path $PROFILE)) { New-Item -Type File -Path $PROFILE -Force }
#    Add-Content -Path $PROFILE -Value "`n. C:\egov-lamp\cli\docker-php-helpers.ps1"
# ==============================================================================

# Deteksi root folder egov-lamp secara dinamis
$scriptDir = $PSScriptRoot
if (-not $scriptDir -and $MyInvocation.MyCommand.Path) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
if (-not $scriptDir -and $MyInvocation.MyCommand.Definition) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
}

$script:EGOV_ROOT = ""
if ($scriptDir) {
    $script:EGOV_ROOT = Split-Path -Parent $scriptDir
}

if (-not $script:EGOV_ROOT -or -not (Test-Path "$script:EGOV_ROOT\docker-compose.yml")) {
    if (Test-Path "C:\egov-lamp\docker-compose.yml") {
        $script:EGOV_ROOT = "C:\egov-lamp"
    } elseif (Test-Path "$PWD\docker-compose.yml") {
        $script:EGOV_ROOT = "$PWD"
    } elseif (Test-Path "$PWD\..\docker-compose.yml") {
        $script:EGOV_ROOT = (Resolve-Path "$PWD\..").Path
    }
}

# Shortcut command: egov (membuka interactive menu)
function egov {
    & "$script:EGOV_ROOT\cli\egov.bat" @args
}

function Invoke-EgovDocker {
    param(
        [Parameter(Mandatory=$true, Position=0)][string]$PhpVer,
        [Parameter(ValueFromRemainingArguments=$true)][string[]]$CmdArgs
    )

    $container = "egov-$PhpVer"

    # Cek apakah container sedang running
    $isRunning = docker ps --format '{{.Names}}' | Where-Object { $_ -eq $container }

    if (-not $isRunning) {
        Write-Host "Container $container belum aktif. Menyalakan otomatis..." -ForegroundColor Yellow
        Push-Location $script:EGOV_ROOT
        docker compose up -d database $PhpVer
        Pop-Location
    }

    # Tentukan path relatif terhadap direktori www
    $hostCwd = (Get-Location).Path
    $containerCwd = "/var/www/html"

    if ($hostCwd -match '[\\/]www[\\/](.+)$') {
        $sub = $Matches[1].Replace('\', '/')
        $containerCwd = "/var/www/html/$sub"
    } elseif ($hostCwd -match '[\\/]www$') {
        $containerCwd = "/var/www/html"
    }

    # Cek apakah perintah membutuhkan TTY interaktif (seperti bash, sh, tinker)
    $needsTty = $false
    foreach ($arg in $CmdArgs) {
        if ($arg -in @("tinker", "bash", "sh", "-a")) {
            $needsTty = $true
            break
        }
    }

    # Jalankan perintah: jika bukan interactive REPL/shell, jalankan tanpa flag -it agar tidak deadlock TTY di Windows
    if ($needsTty) {
        docker exec -it -w $containerCwd $container @CmdArgs
    } else {
        docker exec -w $containerCwd $container @CmdArgs
    }
}

function Get-EgovTargetPhp {
    $currentDir = (Get-Location).Path
    while ($currentDir -and (Split-Path -Parent $currentDir) -ne $currentDir) {
        $wsFile = Join-Path $currentDir ".ws"
        if (Test-Path $wsFile) {
            $line = Get-Content $wsFile | Where-Object { $_ -match '^php=' } | Select-Object -First 1
            if ($line) {
                $ver = ($line -replace '^php=', '').Trim()
                switch ($ver) {
                    "7.4" { return "php74" }
                    "8.0" { return "php80" }
                    "8.1" { return "php81" }
                    "8.2" { return "php82" }
                    "8.3" { return "php83" }
                }
            }
        }
        $currentDir = Split-Path -Parent $currentDir
    }
    return "php74"
}

# Smart CLI Auto-Routing berdasarkan .ws file di project
function php {
    $current = (Get-Location).Path
    if ($current -notmatch '[\\/]www' -and $current -notmatch '[\\/]workspace') {
        $hostPhp = (Get-Command php.exe -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1).Source
        if ($hostPhp) {
            & $hostPhp @args
            return
        }
    }
    $target = Get-EgovTargetPhp
    Invoke-EgovDocker $target "php" @args
}

function artisan {
    $target = Get-EgovTargetPhp
    Invoke-EgovDocker $target "php" "artisan" @args
}

function composer {
    $current = (Get-Location).Path
    if ($current -notmatch '[\\/]www' -and $current -notmatch '[\\/]workspace') {
        $hostComp = (Get-Command composer.bat, composer.phar -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1).Source
        if ($hostComp) {
            & $hostComp @args
            return
        }
    }
    $target = Get-EgovTargetPhp
    Invoke-EgovDocker $target "composer" @args
}

# Explicit PHP CLI
function php74 { Invoke-EgovDocker "php74" "php" @args }
function php80 { Invoke-EgovDocker "php80" "php" @args }
function php81 { Invoke-EgovDocker "php81" "php" @args }
function php82 { Invoke-EgovDocker "php82" "php" @args }
function php83 { Invoke-EgovDocker "php83" "php" @args }

# Explicit Composer CLI
function composer74 { Invoke-EgovDocker "php74" "composer" @args }
function composer80 { Invoke-EgovDocker "php80" "composer" @args }
function composer81 { Invoke-EgovDocker "php81" "composer" @args }
function composer82 { Invoke-EgovDocker "php82" "composer" @args }
function composer83 { Invoke-EgovDocker "php83" "composer" @args }

# Explicit Artisan CLI
function artisan74 { Invoke-EgovDocker "php74" "php" "artisan" @args }
function artisan80 { Invoke-EgovDocker "php80" "php" "artisan" @args }
function artisan81 { Invoke-EgovDocker "php81" "php" "artisan" @args }
function artisan82 { Invoke-EgovDocker "php82" "php" "artisan" @args }
# Fix permissions untuk Laravel storage, cache, .ws, dan vhosts
function fix-perms {
    $container = docker ps --format '{{.Names}}' | Where-Object { $_ -match '^egov-php' } | Select-Object -First 1
    if (-not $container) {
        Write-Host "Tidak ada container PHP yang aktif." -ForegroundColor Red
        return
    }
    Write-Host "Memperbaiki permission storage, cache, .ws, dan vhosts..." -ForegroundColor Yellow
    docker exec $container bash -c '
        chmod -R 777 /etc/apache2/sites-enabled 2>/dev/null
        for d in /var/www/html/*/ ; do
            [ -f "$d/.ws" ] && chmod 666 "$d/.ws" 2>/dev/null
            if [ -d "$d/storage" ] || [ -d "$d/bootstrap/cache" ]; then
                chmod -R 777 "$d/storage" "$d/bootstrap/cache" 2>/dev/null
                echo "✔ Fixed: $(basename "$d")"
            fi
        done
    '
    Write-Host "Selesai! Semua permission sudah aman." -ForegroundColor Green
}

Write-Host "EGOV-LAMP PowerShell Helpers loaded!" -ForegroundColor Green
Write-Host "Commands available: egov, php, composer, artisan, php74..83, composer74..83, artisan74..83, fix-perms" -ForegroundColor Cyan

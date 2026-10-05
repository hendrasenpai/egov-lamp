# ==============================================================================
# GOV-LAMP CLI HELPERS FOR WINDOWS POWERSHELL
# ==============================================================================
# Cara pemakaian di PowerShell:
# 1. Untuk sesi saat ini:
#    . C:\gov-lamp\cli\docker-php-helpers.ps1
#
# 2. Agar aktif permanen di setiap buka PowerShell:
#    if (!(Test-Path $PROFILE)) { New-Item -Type File -Path $PROFILE -Force }
#    Add-Content -Path $PROFILE -Value "`n. C:\gov-lamp\cli\docker-php-helpers.ps1"
# ==============================================================================

# Deteksi root folder gov-lamp secara dinamis
$scriptDir = $PSScriptRoot
if (-not $scriptDir -and $MyInvocation.MyCommand.Path) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
if (-not $scriptDir -and $MyInvocation.MyCommand.Definition) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
}

$script:GOV_ROOT = ""
if ($scriptDir) {
    $script:GOV_ROOT = Split-Path -Parent $scriptDir
}

if (-not $script:GOV_ROOT -or -not (Test-Path "$script:GOV_ROOT\docker-compose.yml")) {
    if (Test-Path "C:\gov-lamp\docker-compose.yml") {
        $script:GOV_ROOT = "C:\gov-lamp"
    } elseif (Test-Path "$PWD\docker-compose.yml") {
        $script:GOV_ROOT = "$PWD"
    } elseif (Test-Path "$PWD\..\docker-compose.yml") {
        $script:GOV_ROOT = (Resolve-Path "$PWD\..").Path
    }
}

# Shortcut command: gov (membuka interactive menu)
function gov {
    & "$script:GOV_ROOT\cli\gov.bat" @args
}

function Invoke-GovDocker {
    param(
        [Parameter(Mandatory=$true, Position=0)][string]$PhpVer,
        [Parameter(ValueFromRemainingArguments=$true)][string[]]$CmdArgs
    )

    $container = "gov-$PhpVer"

    # Cek apakah container sedang running
    $isRunning = docker ps --format '{{.Names}}' | Where-Object { $_ -eq $container }
    if (-not $isRunning) {
        Write-Host "Container $container belum aktif. Menyalakan otomatis..." -ForegroundColor Yellow
        Push-Location $script:GOV_ROOT
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

function Get-GovTargetPhp {
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
function artisan {
    $target = Get-GovTargetPhp
    Invoke-GovDocker $target "php" "artisan" @args
}

function composer {
    $target = Get-GovTargetPhp
    Invoke-GovDocker $target "composer" @args
}

# Explicit PHP CLI
function php74 { Invoke-GovDocker "php74" "php" @args }
function php80 { Invoke-GovDocker "php80" "php" @args }
function php81 { Invoke-GovDocker "php81" "php" @args }
function php82 { Invoke-GovDocker "php82" "php" @args }
function php83 { Invoke-GovDocker "php83" "php" @args }

# Explicit Composer CLI
function composer74 { Invoke-GovDocker "php74" "composer" @args }
function composer80 { Invoke-GovDocker "php80" "composer" @args }
function composer81 { Invoke-GovDocker "php81" "composer" @args }
function composer82 { Invoke-GovDocker "php82" "composer" @args }
function composer83 { Invoke-GovDocker "php83" "composer" @args }

# Explicit Artisan CLI
function artisan74 { Invoke-GovDocker "php74" "php" "artisan" @args }
function artisan80 { Invoke-GovDocker "php80" "php" "artisan" @args }
function artisan81 { Invoke-GovDocker "php81" "php" "artisan" @args }
function artisan82 { Invoke-GovDocker "php82" "php" "artisan" @args }
function artisan83 { Invoke-GovDocker "php83" "php" "artisan" @args }

Write-Host "GOV-LAMP PowerShell Helpers loaded!" -ForegroundColor Green
Write-Host "Commands available: gov, php74..83, composer74..83, artisan74..83, composer, artisan" -ForegroundColor Cyan

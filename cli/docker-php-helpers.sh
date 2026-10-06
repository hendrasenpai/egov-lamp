#!/bin/bash
# ==============================================================================
# EGOV-LAMP CLI HELPERS & SMART ROUTER
# Source file ini di ~/.bashrc atau ~/.zshrc:
# source /path/to/egov-lamp/cli/docker-php-helpers.sh
# ==============================================================================

# Auto-detect root directory of egov-lamp (kompatibel Bash & Zsh)
if [ -n "$BASH_SOURCE" ]; then
    _EGOV_SCRIPT_SRC="${BASH_SOURCE[0]}"
else
    _EGOV_SCRIPT_SRC="$0"
fi
_EGOV_ROOT="$(cd "$(dirname "$_EGOV_SCRIPT_SRC")/.." 2>/dev/null && pwd)"
[ -z "$_EGOV_ROOT" ] && _EGOV_ROOT="$PWD"

# Shortcut Command: egov (Langsung jalankan menu interactive)
egov() {
    if [ -f "$_EGOV_ROOT/cli/egov" ]; then
        bash "$_EGOV_ROOT/cli/egov" "$@"
    else
        echo -e "\033[0;31mError: Script launcher tidak ditemukan di $_EGOV_ROOT/cli/egov\033[0m"
    fi
}

_run_egov_docker() {
    local php_ver="$1"
    shift
    local container="egov-$php_ver"

    # Periksa apakah container aktif
    if ! docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
        echo -e "\033[1;33mContainer $container belum aktif. Menyalakan otomatis...\033[0m"
        (cd "$_EGOV_ROOT" && docker compose up -d database "$php_ver")
    fi

    # Tentukan path relatif terhadap direktori www
    local host_cwd="$PWD"
    local container_cwd="/var/www/html"

    if [[ "$host_cwd" == *"/www/"* ]]; then
        local subpath="${host_cwd#*/www/}"
        container_cwd="/var/www/html/$subpath"
    elif [[ "$host_cwd" == *"/www" ]]; then
        container_cwd="/var/www/html"
    fi

    local tty_flag=""
    [ -t 0 ] && [ -t 1 ] && tty_flag="-it" || tty_flag="-i"
    docker exec $tty_flag -w "$container_cwd" "$container" "$@"
}

# Auto-detect versi PHP dari file .ws di direktori project saat ini
_get_egov_target_php() {
    local dir="$PWD"
    while [[ "$dir" != "/" && "$dir" != "$HOME" ]]; do
        if [ -f "$dir/.ws" ]; then
            local v=$(grep -E '^php=' "$dir/.ws" | cut -d'=' -f2 | tr -d '[:space:]')
            case "$v" in
                7.4) echo "php74"; return ;;
                8.0) echo "php80"; return ;;
                8.1) echo "php81"; return ;;
                8.2) echo "php82"; return ;;
                8.3) echo "php83"; return ;;
            esac
        fi
        dir="$(dirname "$dir")"
    done
    echo "php74" # Default fallback
}

# Smart CLI Auto-Routing
php() {
    # Jika berada di luar workspace dan ada binary PHP asli di host laptop, gunakan host
    if [[ "$PWD" != "$_EGOV_ROOT"* && "$PWD" != *"/workspace/"* ]]; then
        local host_php=$(type -P php 2>/dev/null)
        if [ -n "$host_php" ] && [ -x "$host_php" ]; then
            "$host_php" "$@"
            return $?
        fi
    fi
    local target=$(_get_egov_target_php)
    _run_egov_docker "$target" php "$@"
}

artisan() {
    local target=$(_get_egov_target_php)
    _run_egov_docker "$target" php artisan "$@"
}

composer() {
    # Jika berada di luar workspace dan ada binary Composer asli di host laptop, gunakan host
    if [[ "$PWD" != "$_EGOV_ROOT"* && "$PWD" != *"/workspace/"* ]]; then
        local host_composer=$(type -P composer 2>/dev/null)
        if [ -n "$host_composer" ] && [ -x "$host_composer" ]; then
            "$host_composer" "$@"
            return $?
        fi
    fi
    local target=$(_get_egov_target_php)
    _run_egov_docker "$target" composer "$@"
}
Composer() { composer "$@"; }

# Explicit PHP CLI
php74() { _run_egov_docker "php74" php "$@"; }
php80() { _run_egov_docker "php80" php "$@"; }
php81() { _run_egov_docker "php81" php "$@"; }
php82() { _run_egov_docker "php82" php "$@"; }
php83() { _run_egov_docker "php83" php "$@"; }

# Explicit Composer CLI
composer74() { _run_egov_docker "php74" composer "$@"; }
composer80() { _run_egov_docker "php80" composer "$@"; }
composer81() { _run_egov_docker "php81" composer "$@"; }
composer82() { _run_egov_docker "php82" composer "$@"; }
composer83() { _run_egov_docker "php83" composer "$@"; }

# Explicit Artisan CLI
artisan74() { _run_egov_docker "php74" php artisan "$@"; }
artisan80() { _run_egov_docker "php80" php artisan "$@"; }
artisan81() { _run_egov_docker "php81" php artisan "$@"; }
artisan82() { _run_egov_docker "php82" php artisan "$@"; }
artisan83() { _run_egov_docker "php83" php artisan "$@"; }

# Fix permissions untuk Laravel storage, cache, .ws, dan vhosts
fix-perms() {
    echo -e "\033[1;33mMemperbaiki permission storage, cache, .ws, dan folder project...\033[0m"
    # 1. Perbaiki di host lokal jika direktori www ada
    if [ -d "$_EGOV_ROOT/www" ]; then
        chmod 777 "$_EGOV_ROOT/www" 2>/dev/null
        for p in "$_EGOV_ROOT/www"/*; do
            if [ -d "$p" ]; then
                chmod 777 "$p" 2>/dev/null
                touch "$p/.ws" 2>/dev/null
                chmod 666 "$p/.ws" 2>/dev/null
                [ -d "$p/storage" ] && chmod -R 777 "$p/storage" 2>/dev/null
                [ -d "$p/bootstrap/cache" ] && chmod -R 777 "$p/bootstrap/cache" 2>/dev/null
            fi
        done
    fi

    # 2. Perbaiki di dalam container
    local container=$(docker ps --format '{{.Names}}' | grep -E '^egov-php' | head -n 1)
    if [ -n "$container" ]; then
        docker exec "$container" bash -c '
            chmod -R 777 /etc/apache2/sites-enabled 2>/dev/null
            chmod 777 /var/www/html 2>/dev/null
            for d in /var/www/html/*/ ; do
                chmod 777 "$d" 2>/dev/null
                touch "$d/.ws" 2>/dev/null
                chmod 666 "$d/.ws" 2>/dev/null
                if [ -d "$d/storage" ] || [ -d "$d/bootstrap/cache" ]; then
                    chmod -R 777 "$d/storage" "$d/bootstrap/cache" 2>/dev/null
                fi
                echo "✔ Fixed: $(basename "$d")"
            done
        '
    fi
    echo -e "\033[0;32mSelesai! Semua permission sudah aman.\033[0m"
}

# Integrasi Editor Google Antigravity IDE dari dalam WSL ke Windows
if grep -qiE "microsoft|wsl" /proc/version 2>/dev/null; then
    antigravity() {
        local target="${1:-.}"
        local abs_target
        abs_target=$(readlink -f "$target" 2>/dev/null || echo "$target")

        local win_path
        if command -v wslpath >/dev/null 2>&1; then
            win_path=$(wslpath -w "$abs_target" 2>/dev/null || echo "$target")
        else
            win_path="$target"
        fi

        local distro="${WSL_DISTRO_NAME:-Ubuntu}"

        # 1. Prioritaskan Antigravity IDE (Code Editor) di Windows PATH
        for cmd in antigravity-ide.cmd antigravity-ide antigravity-ide.exe agy-ide.cmd; do
            if command -v "$cmd" >/dev/null 2>&1; then
                "$cmd" --remote "wsl+$distro" "$abs_target" 2>/dev/null || "$cmd" "$win_path" 2>/dev/null &
                return
            fi
        done

        # 2. Cari instalasi spesifik Antigravity IDE di Program Files & AppData Windows
        local ide_candidates=(
            # Program Files (64-bit)
            "/mnt/c/Program Files/Google/Antigravity IDE/bin/antigravity-ide"*
            "/mnt/c/Program Files/Google/Antigravity IDE/Antigravity IDE.exe"
            "/mnt/c/Program Files/Antigravity IDE/bin/antigravity-ide"*
            "/mnt/c/Program Files/Antigravity IDE/Antigravity IDE.exe"
            "/mnt/c/Program Files/Google/Antigravity/bin/antigravity"*
            "/mnt/c/Program Files/Antigravity/bin/antigravity"*
            "/mnt/c/Program Files/Antigravity/Antigravity.exe"
            # Program Files (x86)
            "/mnt/c/Program Files (x86)/Google/Antigravity IDE/bin/antigravity-ide"*
            "/mnt/c/Program Files (x86)/Google/Antigravity IDE/Antigravity IDE.exe"
            "/mnt/c/Program Files (x86)/Antigravity IDE/bin/antigravity-ide"*
            "/mnt/c/Program Files (x86)/Antigravity IDE/Antigravity IDE.exe"
            "/mnt/c/Program Files (x86)/Antigravity/bin/antigravity"*
            # User AppData - Antigravity IDE
            /mnt/c/Users/*/AppData/Local/Programs/"Antigravity IDE"/bin/antigravity-ide*
            /mnt/c/Users/*/AppData/Local/Programs/"Antigravity IDE"/"Antigravity IDE.exe"
            /mnt/c/Users/*/AppData/Local/Programs/"Antigravity"/bin/antigravity*
            /mnt/c/Users/*/AppData/Local/Programs/Google/"Antigravity IDE"/"Antigravity IDE.exe"
        )

        for exe in "${ide_candidates[@]}"; do
            if [ -f "$exe" ]; then
                if [[ "$exe" == *"bin/antigravity"* ]] || [[ "$exe" == *".cmd" ]]; then
                    "$exe" --remote "wsl+$distro" "$abs_target" 2>/dev/null || "$exe" "$win_path" 2>/dev/null &
                else
                    "$exe" "$win_path" 2>/dev/null &
                fi
                return
            fi
        done

        # 3. Fallback: jika Antigravity IDE belum ada, cek apakah hanya ada Antigravity 2.0 (Desktop Agent App)
        for u in /mnt/c/Users/*; do
            if [ -f "$u/AppData/Local/Programs/Antigravity/Antigravity.exe" ]; then
                echo -e "\033[1;33m[Perhatian]\033[0m Antigravity IDE (Editor Kode) tidak ditemukan, membuka Antigravity 2.0 (Desktop App)."
                echo -e "Untuk membuka editor koding (seperti VS Code), pastikan telah menginstall \033[1;32mAntigravity IDE\033[0m di Windows."
                "$u/AppData/Local/Programs/Antigravity/Antigravity.exe" "$win_path" 2>/dev/null &
                return
            fi
        done

        # 4. Fallback cmd.exe start
        cmd.exe /c start "" "antigravity-ide" "$win_path" 2>/dev/null || \
        cmd.exe /c start "" "antigravity" "$win_path" 2>/dev/null || {
            echo -e "\033[0;31mAntigravity IDE tidak ditemukan di Windows.\033[0m"
            echo -e "Pastikan Anda telah menginstall \033[1;36mAntigravity IDE\033[0m di Windows."
            echo -e "Buka Antigravity IDE, tekan \033[1;33mCtrl+Shift+P\033[0m, lalu pilih:"
            echo -e "\033[1;33mShell Command: Install 'antigravity-ide' command in PATH\033[0m"
        }
    }

    alias antigravity-ide='antigravity'
    alias agy-ide='antigravity'
    alias ide='antigravity'
fi


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
    local container=$(docker ps --format '{{.Names}}' | grep -E '^egov-php' | head -n 1)
    if [ -z "$container" ]; then
        echo -e "\033[0;31mTidak ada container PHP yang aktif.\033[0m"
        return 1
    fi
    echo -e "\033[1;33mMemperbaiki permission storage, cache, .ws, dan vhosts...\033[0m"
    docker exec "$container" bash -c '
        chmod -R 777 /etc/apache2/sites-enabled 2>/dev/null
        for d in /var/www/html/*/ ; do
            [ -f "$d/.ws" ] && chmod 666 "$d/.ws" 2>/dev/null
            if [ -d "$d/storage" ] || [ -d "$d/bootstrap/cache" ]; then
                chmod -R 777 "$d/storage" "$d/bootstrap/cache" 2>/dev/null
                echo "✔ Fixed: $(basename "$d")"
            fi
        done
    '
    echo -e "\033[0;32mSelesai! Semua permission sudah aman.\033[0m"
}

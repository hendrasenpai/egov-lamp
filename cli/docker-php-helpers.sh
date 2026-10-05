#!/bin/bash
# ==============================================================================
# GOV-LAMP CLI HELPERS & SMART ROUTER
# Source file ini di ~/.bashrc atau ~/.zshrc:
# source /path/to/gov-lamp/cli/docker-php-helpers.sh
# ==============================================================================

# Auto-detect root directory of gov-lamp (kompatibel Bash & Zsh)
if [ -n "$BASH_SOURCE" ]; then
    _GOV_SCRIPT_SRC="${BASH_SOURCE[0]}"
else
    _GOV_SCRIPT_SRC="$0"
fi
_GOV_ROOT="$(cd "$(dirname "$_GOV_SCRIPT_SRC")/.." 2>/dev/null && pwd)"
[ -z "$_GOV_ROOT" ] && _GOV_ROOT="$PWD"

# Shortcut Command: gov (Langsung jalankan menu interactive tanpa perlu symlink)
gov() {
    if [ -f "$_GOV_ROOT/cli/gov" ]; then
        bash "$_GOV_ROOT/cli/gov" "$@"
    else
        echo -e "\033[0;31mError: Script launcher tidak ditemukan di $_GOV_ROOT/cli/gov\033[0m"
    fi
}

_run_gov_docker() {
    local php_ver="$1"
    shift
    local container="gov-$php_ver"

    # Periksa apakah container aktif
    if ! docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
        echo -e "\033[1;33mContainer $container belum aktif. Menyalakan otomatis...\033[0m"
        (cd "$_GOV_ROOT" && docker compose up -d database "$php_ver")
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

    docker exec -it -w "$container_cwd" "$container" "$@"
}

# Auto-detect versi PHP dari file .ws di direktori project saat ini
_get_gov_target_php() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
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
artisan() {
    local target=$(_get_gov_target_php)
    _run_gov_docker "$target" php artisan "$@"
}

composer() {
    local target=$(_get_gov_target_php)
    _run_gov_docker "$target" composer "$@"
}

# Explicit PHP CLI
php74() { _run_gov_docker "php74" php "$@"; }
php80() { _run_gov_docker "php80" php "$@"; }
php81() { _run_gov_docker "php81" php "$@"; }
php82() { _run_gov_docker "php82" php "$@"; }
php83() { _run_gov_docker "php83" php "$@"; }

# Explicit Composer CLI
composer74() { _run_gov_docker "php74" composer "$@"; }
composer80() { _run_gov_docker "php80" composer "$@"; }
composer81() { _run_gov_docker "php81" composer "$@"; }
composer82() { _run_gov_docker "php82" composer "$@"; }
composer83() { _run_gov_docker "php83" composer "$@"; }

# Explicit Artisan CLI
artisan74() { _run_gov_docker "php74" php artisan "$@"; }
artisan80() { _run_gov_docker "php80" php artisan "$@"; }
artisan81() { _run_gov_docker "php81" php artisan "$@"; }
artisan82() { _run_gov_docker "php82" php artisan "$@"; }
artisan83() { _run_gov_docker "php83" php artisan "$@"; }

# Fix permissions untuk Laravel storage, cache, .ws, dan vhosts
fix-perms() {
    local container=$(docker ps --format '{{.Names}}' | grep -E '^gov-php' | head -n 1)
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

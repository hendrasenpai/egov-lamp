#!/bin/bash
# ==============================================================================
# EGOV-LAMP PROJECT INITIALIZER (CORE LARAVEL TEMPLATE)
# Standard Template Inisialisasi Project Laravel Diskominfo Bintan
# Repo Template: https://github.com/tim-it-diskominfobintan/core-laravel
# ==============================================================================

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Auto-detect root directory of egov-lamp
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/../docker-compose.yml" ]; then
    ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
elif [ -f "./docker-compose.yml" ]; then
    ROOT_DIR="$(pwd)"
elif [ -d "/home/hendra/workspace/egov" ]; then
    ROOT_DIR="/home/hendra/workspace/egov"
else
    ROOT_DIR="$(pwd)"
fi

TEMPLATE_REPO="https://github.com/tim-it-diskominfobintan/core-laravel.git"
SSH_TEMPLATE_REPO="git@github.com:tim-it-diskominfobintan/core-laravel.git"

PROJECT_NAME="$1"
PHP_INPUT="$2"
REMOTE_URL="$3"

echo ""
echo -e "${CYAN}=====================================================${NC}"
echo -e "${CYAN}   🚀 INISIALISASI PROJECT BARU (CORE LARAVEL)       ${NC}"
echo -e "${CYAN}=====================================================${NC}"
echo -e "Template Resmi : ${YELLOW}$TEMPLATE_REPO${NC}"
echo -e "Direktori Host : ${YELLOW}$ROOT_DIR/www/${NC}"
echo ""

# 1. Input nama project
if [ -z "$PROJECT_NAME" ]; then
    echo -e "Masukkan nama project baru (nama folder di www/):"
    read -p "Nama Project: " PROJECT_NAME
fi

# Sanitasi nama project
PROJECT_NAME=$(echo "$PROJECT_NAME" | sed 's/[^a-zA-Z0-9_\-]//g')
if [ -z "$PROJECT_NAME" ]; then
    echo -e "${RED}Nama project tidak valid!${NC}"
    exit 1
fi

TARGET_DIR="$ROOT_DIR/www/$PROJECT_NAME"
if [ -d "$TARGET_DIR" ]; then
    echo -e "${RED}Error: Folder www/$PROJECT_NAME sudah ada! Silakan gunakan nama lain.${NC}"
    exit 1
fi

# 2. Token GitHub & Clone URL
TOKEN_FILE="$ROOT_DIR/www/.github_token"
CLONE_URL="$TEMPLATE_REPO"
if [ -f "$TOKEN_FILE" ]; then
    TOKEN=$(cat "$TOKEN_FILE" | tr -d '[:space:]')
    if [ -n "$TOKEN" ]; then
        CLONE_URL="https://oauth2:${TOKEN}@github.com/tim-it-diskominfobintan/core-laravel.git"
    fi
fi

# 3. Pilihan Versi PHP
if [ -z "$PHP_INPUT" ]; then
    echo ""
    echo -e "${CYAN}Pilih versi PHP untuk project ini:${NC}"
    echo -e "  ${BOLD}1)${NC} PHP 8.3 (Port :8083 - Rekomendasi Laravel Terbaru)"
    echo -e "  ${BOLD}2)${NC} PHP 8.2 (Port :8082)"
    echo -e "  ${BOLD}3)${NC} PHP 8.1 (Port :8081)"
    read -p "Pilihan versi [1-3] (default 1): " PHP_CHOICE
    case $PHP_CHOICE in
        2|8.2|82) PHP_VER="8.2"; PHP_CONTAINER="php82"; PORT="8082" ;;
        3|8.1|81) PHP_VER="8.1"; PHP_CONTAINER="php81"; PORT="8081" ;;
        *)        PHP_VER="8.3"; PHP_CONTAINER="php83"; PORT="8083" ;;
    esac
else
    case $PHP_INPUT in
        2|8.2|82) PHP_VER="8.2"; PHP_CONTAINER="php82"; PORT="8082" ;;
        3|8.1|81) PHP_VER="8.1"; PHP_CONTAINER="php81"; PORT="8081" ;;
        *)        PHP_VER="8.3"; PHP_CONTAINER="php83"; PORT="8083" ;;
    esac
fi

# 4. Input Remote GitHub Baru (Opsional)
if [ -z "$REMOTE_URL" ]; then
    echo ""
    echo -e "Masukkan URL Remote GitHub baru untuk project ini (kosongkan jika belum ada):"
    echo -e "Contoh: https://github.com/tim-it-diskominfobintan/$PROJECT_NAME.git"
    read -p "Remote Origin URL: " REMOTE_URL
fi

# 5. Clone core-laravel
echo ""
echo -e "${YELLOW}Meng-clone template core-laravel ke www/$PROJECT_NAME...${NC}"
git clone "$CLONE_URL" "$TARGET_DIR"
if [ $? -ne 0 ]; then
    echo ""
    echo -e "${RED}Gagal clone template core-laravel!${NC}"
    echo -e "${YELLOW}Pastikan token GitHub sudah diset di web dashboard atau SSH Key GitHub terdaftar.${NC}"
    exit 1
fi
echo -e "${GREEN}✔ Berhasil clone template core-laravel!${NC}"

# 6. Reset Git History
echo -e "${YELLOW}Mereset Git history (rm -rf .git && git init -b main)...${NC}"
rm -rf "$TARGET_DIR/.git"
git -C "$TARGET_DIR" init -b main >/dev/null 2>&1
if [ -n "$REMOTE_URL" ]; then
    git -C "$TARGET_DIR" remote add origin "$REMOTE_URL" 2>/dev/null
    echo -e "${GREEN}✔ Remote origin diset ke: $REMOTE_URL${NC}"
fi

# 7. Setup .ws & .code-workspace
echo "php=$PHP_VER" > "$TARGET_DIR/.ws"
echo "type=laravel" >> "$TARGET_DIR/.ws"
echo "entry=public" >> "$TARGET_DIR/.ws"
echo "ide=auto" >> "$TARGET_DIR/.ws"
echo "icon=auto" >> "$TARGET_DIR/.ws"
chmod 666 "$TARGET_DIR/.ws" 2>/dev/null

WS_FILE="$TARGET_DIR/$PROJECT_NAME.code-workspace"
cat <<EOL > "$WS_FILE"
{
  "folders": [
    {
      "path": "."
    }
  ],
  "settings": {}
}
EOL
chmod 666 "$WS_FILE" 2>/dev/null

# 8. Setup .env & Database MariaDB
DB_SAFE_NAME=$(echo "$PROJECT_NAME" | sed 's/[^a-zA-Z0-9_]/_/g')

if [ -f "$TARGET_DIR/.env.example" ] && [ ! -f "$TARGET_DIR/.env" ]; then
    cp "$TARGET_DIR/.env.example" "$TARGET_DIR/.env"
    sed -i 's/^DB_HOST=.*/DB_HOST=database/' "$TARGET_DIR/.env"
    sed -i 's/^DB_PORT=.*/DB_PORT=3306/' "$TARGET_DIR/.env"
    sed -i 's/^DB_USERNAME=.*/DB_USERNAME=root/' "$TARGET_DIR/.env"
    sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=tiger/' "$TARGET_DIR/.env"
    APP_NAME_FORMATTED=$(echo "$PROJECT_NAME" | sed 's/[_-]/ /g' | awk '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) substr($i,2)} 1')
    sed -i "s/^APP_NAME=.*/APP_NAME=\"$APP_NAME_FORMATTED\"/" "$TARGET_DIR/.env"
    sed -i 's/^REDIS_HOST=.*/REDIS_HOST=redis/' "$TARGET_DIR/.env"
    echo -e "${GREEN}✔ File .env dikonfigurasi untuk MariaDB Docker (DB: $DB_SAFE_NAME, App: $APP_NAME_FORMATTED).${NC}"
fi

echo -e "${YELLOW}Membuat database MariaDB '$DB_SAFE_NAME'...${NC}"
(cd "$ROOT_DIR" && docker compose exec -T database mysql -u root -ptiger -e "CREATE DATABASE IF NOT EXISTS \`$DB_SAFE_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true)

# 9. Permissions
chmod -R 777 "$TARGET_DIR" 2>/dev/null
[ -d "$TARGET_DIR/storage" ] && chmod -R 777 "$TARGET_DIR/storage" 2>/dev/null
[ -d "$TARGET_DIR/bootstrap/cache" ] && chmod -R 777 "$TARGET_DIR/bootstrap/cache" 2>/dev/null

# 10. Composer Install & Artisan Pipeline
echo -e "${YELLOW}Memastikan container egov-$PHP_CONTAINER & database aktif...${NC}"
(cd "$ROOT_DIR" && docker compose up -d database redis "$PHP_CONTAINER")

echo -e "${YELLOW}Menjalankan composer install...${NC}"
docker exec -it -w "/var/www/html/$PROJECT_NAME" -e COMPOSER_ALLOW_SUPERUSER=1 "egov-$PHP_CONTAINER" composer install --no-interaction

if [ -f "$TARGET_DIR/artisan" ]; then
    echo -e "${YELLOW}Menjalankan key:generate & storage:link...${NC}"
    docker exec -it -w "/var/www/html/$PROJECT_NAME" "egov-$PHP_CONTAINER" php artisan key:generate --force
    docker exec -it -w "/var/www/html/$PROJECT_NAME" "egov-$PHP_CONTAINER" php artisan storage:link 2>/dev/null || true

    # Auto-patch upstream migration timestamp conflict in laravel-core-functions
    CONFLICT_MIG="$TARGET_DIR/vendor/tim-it-diskominfobintan/laravel-core-functions/database/migrations/0001_01_01_000007_create_profile_role_bindings_table.php"
    FIXED_MIG="$TARGET_DIR/vendor/tim-it-diskominfobintan/laravel-core-functions/database/migrations/2025_06_10_144612_create_profile_role_bindings_table.php"
    if [ -f "$CONFLICT_MIG" ]; then
        mv "$CONFLICT_MIG" "$FIXED_MIG" 2>/dev/null || true
    fi

    echo -e "${YELLOW}Menjalankan migrasi & seeder (php artisan migrate:fresh --seed)...${NC}"
    docker exec -it -w "/var/www/html/$PROJECT_NAME" "egov-$PHP_CONTAINER" php artisan migrate:fresh --seed --force
fi

# 11. Initial Commit
git -C "$TARGET_DIR" add . >/dev/null 2>&1
git -C "$TARGET_DIR" commit -m "chore: initialize project from core-laravel template" >/dev/null 2>&1
echo -e "${GREEN}✔ Initial Git commit berhasil dibuat!${NC}"

# 12. Buka di IDE
echo ""
read -p "Buka project di Antigravity IDE / Editor sekarang? [Y/n]: " OPEN_CONFIRM
if [[ "$OPEN_CONFIRM" =~ ^[Yy]$ || -z "$OPEN_CONFIRM" ]]; then
    if type antigravity >/dev/null 2>&1; then
        antigravity "$TARGET_DIR"
    elif [ -f "$ROOT_DIR/cli/docker-php-helpers.sh" ]; then
        source "$ROOT_DIR/cli/docker-php-helpers.sh" 2>/dev/null
        antigravity "$TARGET_DIR" 2>/dev/null || code "$TARGET_DIR" 2>/dev/null
    else
        code "$TARGET_DIR" 2>/dev/null || true
    fi
fi

# 13. Ringkasan
echo ""
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}   🎉 PROJECT $PROJECT_NAME SIAP DIGUNAKAN!          ${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo -e "Nama Project : ${BOLD}$PROJECT_NAME${NC}"
echo -e "Lokasi Host  : ${YELLOW}$TARGET_DIR${NC}"
echo -e "Versi PHP    : ${CYAN}PHP $PHP_VER (Container: egov-$PHP_CONTAINER)${NC}"
echo -e "Akses Web    : ${CYAN}http://localhost:$PORT/$PROJECT_NAME/public${NC}"
echo -e "Database     : MariaDB (Host: ${CYAN}database${NC}, DB: ${CYAN}$DB_SAFE_NAME${NC}, User: ${CYAN}root${NC}, Pass: ${CYAN}tiger${NC})"
if [ -n "$REMOTE_URL" ]; then
    echo -e "Remote Git   : ${CYAN}$REMOTE_URL${NC} (Siap: git push -u origin main)"
else
    echo -e "Remote Git   : ${YELLOW}Belum dihubungkan ke GitHub${NC}"
fi
echo ""

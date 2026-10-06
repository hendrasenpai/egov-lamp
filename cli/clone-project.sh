#!/bin/bash
# ==============================================================================
# EGOV-LAMP PROJECT CLONER
# Otomatis clone repo dari https://github.com/tim-it-diskominfobintan
# dan setup lingkungan (PHP .ws, .env, permissions, composer, dsb)
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

ORG_BASE_HTTPS="https://github.com/tim-it-diskominfobintan"
ORG_BASE_SSH="git@github.com:tim-it-diskominfobintan"

REPO_INPUT="$1"
PHP_INPUT="$2"

echo ""
echo -e "${CYAN}=====================================================${NC}"
echo -e "${CYAN}   📦 CLONE REPOSITORY DISKOMINFO BINTAN             ${NC}"
echo -e "${CYAN}=====================================================${NC}"
echo -e "Organisasi Default : ${YELLOW}$ORG_BASE_HTTPS${NC}"
echo -e "Direktori Tujuan   : ${YELLOW}$ROOT_DIR/www/${NC}"
echo ""

# 1. Dapatkan nama / URL repository
if [ -z "$REPO_INPUT" ]; then
    echo -e "Masukkan nama repository atau full URL GitHub."
    echo -e "Contoh nama: ${BOLD}web_bintan${NC}, ${BOLD}b-smart${NC}, ${BOLD}ak1_disnaker${NC}"
    read -p "Nama / URL Repo: " REPO_INPUT
fi

if [ -z "$REPO_INPUT" ]; then
    echo -e "${RED}Nama repository tidak boleh kosong!${NC}"
    exit 1
fi

# Parsing nama folder dan URL clone
if [[ "$REPO_INPUT" =~ ^https?:// ]] || [[ "$REPO_INPUT" =~ ^git@ ]]; then
    CLONE_URL="$REPO_INPUT"
    REPO_NAME=$(basename "$REPO_INPUT" .git)
else
    REPO_NAME="$REPO_INPUT"
    echo ""
    echo -e "Pilih metode clone untuk ${BOLD}$REPO_NAME${NC}:"
    echo -e "  ${BOLD}1)${NC} HTTPS (Default: $ORG_BASE_HTTPS/$REPO_NAME.git)"
    echo -e "  ${BOLD}2)${NC} SSH   ($ORG_BASE_SSH/$REPO_NAME.git)"
    read -p "Pilihan [1/2] (default 1): " PROTO_CHOICE
    if [ "$PROTO_CHOICE" == "2" ]; then
        CLONE_URL="$ORG_BASE_SSH/$REPO_NAME.git"
    else
        CLONE_URL="$ORG_BASE_HTTPS/$REPO_NAME.git"
    fi
fi

TARGET_DIR="$ROOT_DIR/www/$REPO_NAME"

# 2. Cek apakah folder sudah ada di www/
if [ -d "$TARGET_DIR" ]; then
    echo ""
    echo -e "${YELLOW}Peringatan: Folder www/$REPO_NAME sudah ada!${NC}"
    read -p "Apakah ingin melakukan 'git pull' untuk memperbarui kode? [Y/n]: " PULL_CHOICE
    if [[ "$PULL_CHOICE" =~ ^[Yy]$ || -z "$PULL_CHOICE" ]]; then
        echo -e "${YELLOW}Menjalankan git pull di www/$REPO_NAME...${NC}"
        (cd "$TARGET_DIR" && git pull)
    fi
else
    echo ""
    echo -e "${YELLOW}Meng-clone $CLONE_URL ke www/$REPO_NAME...${NC}"
    git clone "$CLONE_URL" "$TARGET_DIR"
    if [ $? -ne 0 ]; then
        echo ""
        echo -e "${RED}Gagal melakukan clone repository!${NC}"
        echo -e "${YELLOW}Tips Pemecahan Masalah:${NC}"
        echo -e "1. Pastikan nama repository benar di organisasi tim-it-diskominfobintan."
        echo -e "2. Jika repository private, pastikan akun GitHub Anda sudah terdaftar sebagai anggota tim."
        echo -e "3. Jika menggunakan SSH, pastikan SSH Key sudah terdaftar di GitHub (lihat FAQ Q9 di README.md)."
        echo -e "4. Anda juga bisa mencoba clone via HTTPS jika SSH terkendala."
        exit 1
    fi
    echo -e "${GREEN}✔ Berhasil di-clone ke www/$REPO_NAME!${NC}"
fi

# 3. Pengaturan Versi PHP (.ws)
if [ -z "$PHP_INPUT" ]; then
    CURRENT_WS=""
    [ -f "$TARGET_DIR/.ws" ] && CURRENT_WS=$(cat "$TARGET_DIR/.ws" | tr -d '[:space:]')
    
    echo ""
    echo -e "${CYAN}Pilih versi PHP untuk project ini:${NC}"
    echo -e "  ${BOLD}1)${NC} PHP 7.4 (Port :8074)"
    echo -e "  ${BOLD}2)${NC} PHP 8.0 (Port :8080)"
    echo -e "  ${BOLD}3)${NC} PHP 8.1 (Port :8081)"
    echo -e "  ${BOLD}4)${NC} PHP 8.2 (Port :8082 - Rekomendasi Default)"
    echo -e "  ${BOLD}5)${NC} PHP 8.3 (Port :8083)"
    if [ -n "$CURRENT_WS" ]; then
        echo -e "  *(Saat ini sudah diset ke PHP $CURRENT_WS)*"
    fi
    read -p "Pilihan versi [1-5] (default 4): " PHP_CHOICE
    case $PHP_CHOICE in
        1|7.4|74) PHP_VER="7.4"; PHP_CONTAINER="php74"; PORT="8074" ;;
        2|8.0|80) PHP_VER="8.0"; PHP_CONTAINER="php80"; PORT="8080" ;;
        3|8.1|81) PHP_VER="8.1"; PHP_CONTAINER="php81"; PORT="8081" ;;
        5|8.3|83) PHP_VER="8.3"; PHP_CONTAINER="php83"; PORT="8083" ;;
        *)        PHP_VER="8.2"; PHP_CONTAINER="php82"; PORT="8082" ;;
    esac
else
    case $PHP_INPUT in
        1|7.4|74) PHP_VER="7.4"; PHP_CONTAINER="php74"; PORT="8074" ;;
        2|8.0|80) PHP_VER="8.0"; PHP_CONTAINER="php80"; PORT="8080" ;;
        3|8.1|81) PHP_VER="8.1"; PHP_CONTAINER="php81"; PORT="8081" ;;
        5|8.3|83) PHP_VER="8.3"; PHP_CONTAINER="php83"; PORT="8083" ;;
        *)        PHP_VER="8.2"; PHP_CONTAINER="php82"; PORT="8082" ;;
    esac
fi

echo "$PHP_VER" > "$TARGET_DIR/.ws"
chmod 666 "$TARGET_DIR/.ws" 2>/dev/null
echo -e "${GREEN}✔ Versi PHP project diset ke PHP $PHP_VER (file .ws dibuat).${NC}"

# 4. Setup Lingkungan Laravel / File .env & Database
DB_SAFE_NAME=$(echo "$REPO_NAME" | sed 's/[^a-zA-Z0-9_]/_/g')

if [ -f "$TARGET_DIR/.env.example" ] && [ ! -f "$TARGET_DIR/.env" ]; then
    echo ""
    read -p "Ditemukan .env.example. Buat file .env dengan konfigurasi database Docker? [Y/n]: " ENV_CONFIRM
    if [[ "$ENV_CONFIRM" =~ ^[Yy]$ || -z "$ENV_CONFIRM" ]]; then
        cp "$TARGET_DIR/.env.example" "$TARGET_DIR/.env"
        # Ganti konfigurasi DB default ke Docker container MariaDB
        sed -i 's/^DB_HOST=.*/DB_HOST=database/' "$TARGET_DIR/.env"
        sed -i 's/^DB_PORT=.*/DB_PORT=3306/' "$TARGET_DIR/.env"
        sed -i 's/^DB_USERNAME=.*/DB_USERNAME=root/' "$TARGET_DIR/.env"
        sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=tiger/' "$TARGET_DIR/.env"
        sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$DB_SAFE_NAME/" "$TARGET_DIR/.env"
        sed -i 's/^REDIS_HOST=.*/REDIS_HOST=redis/' "$TARGET_DIR/.env"
        echo -e "${GREEN}✔ File .env dibuat otomatis (DB_HOST=database, user=root, DB=$DB_SAFE_NAME)!${NC}"

        # Buat database MariaDB secara otomatis
        (cd "$ROOT_DIR" && docker compose exec -T database mysql -u root -ptiger -e "CREATE DATABASE IF NOT EXISTS \`$DB_SAFE_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true)
        echo -e "${GREEN}✔ Database MariaDB '$DB_SAFE_NAME' berhasil dipastikan ada.${NC}"
    fi
fi

# 5. Perbaiki File Permissions (storage, cache, .ws)
chmod -R 777 "$TARGET_DIR" 2>/dev/null
[ -d "$TARGET_DIR/storage" ] && chmod -R 777 "$TARGET_DIR/storage" 2>/dev/null
[ -d "$TARGET_DIR/bootstrap/cache" ] && chmod -R 777 "$TARGET_DIR/bootstrap/cache" 2>/dev/null
chmod 666 "$TARGET_DIR/.ws" 2>/dev/null

# 6. Jalankan Composer Install & Laravel Auto Setup
if [ -f "$TARGET_DIR/composer.json" ]; then
    echo ""
    read -p "Jalankan 'composer install' & auto-setup via Docker PHP $PHP_VER? [Y/n]: " COMP_CONFIRM
    if [[ "$COMP_CONFIRM" =~ ^[Yy]$ || -z "$COMP_CONFIRM" ]]; then
        echo -e "${YELLOW}Memastikan container egov-$PHP_CONTAINER & database aktif...${NC}"
        (cd "$ROOT_DIR" && docker compose up -d database redis "$PHP_CONTAINER")
        
        echo -e "${YELLOW}Menjalankan composer install di dalam container...${NC}"
        docker exec -it -w "/var/www/html/$REPO_NAME" -e COMPOSER_ALLOW_SUPERUSER=1 "egov-$PHP_CONTAINER" composer install --no-interaction
        
        # Jika Laravel: key:generate, storage:link, migrate
        if [ -f "$TARGET_DIR/artisan" ]; then
            if [ -f "$TARGET_DIR/.env" ] && ! grep -q "^APP_KEY=base64:" "$TARGET_DIR/.env" 2>/dev/null; then
                echo -e "${YELLOW}Menjalankan artisan key:generate...${NC}"
                docker exec -it -w "/var/www/html/$REPO_NAME" "egov-$PHP_CONTAINER" php artisan key:generate --force
            fi

            echo -e "${YELLOW}Membuat symlink storage...${NC}"
            docker exec -it -w "/var/www/html/$REPO_NAME" "egov-$PHP_CONTAINER" php artisan storage:link 2>/dev/null || true

            echo ""
            read -p "Jalankan migrasi database (php artisan migrate:fresh --seed)? [Y/n]: " MIGRATE_CONFIRM
            if [[ "$MIGRATE_CONFIRM" =~ ^[Yy]$ || -z "$MIGRATE_CONFIRM" ]]; then
                # Auto-patch upstream migration timestamp conflict in laravel-core-functions
                CONFLICT_MIG="$TARGET_DIR/vendor/tim-it-diskominfobintan/laravel-core-functions/database/migrations/0001_01_01_000007_create_profile_role_bindings_table.php"
                FIXED_MIG="$TARGET_DIR/vendor/tim-it-diskominfobintan/laravel-core-functions/database/migrations/2025_06_10_144612_create_profile_role_bindings_table.php"
                if [ -f "$CONFLICT_MIG" ]; then
                    mv "$CONFLICT_MIG" "$FIXED_MIG" 2>/dev/null || true
                fi

                echo -e "${YELLOW}Menjalankan php artisan migrate:fresh --seed...${NC}"
                docker exec -it -w "/var/www/html/$REPO_NAME" "egov-$PHP_CONTAINER" php artisan migrate:fresh --seed --force
                echo -e "${GREEN}✔ Database migration & seed selesai.${NC}"
            fi
        fi
    fi
fi

# 7. Buka ke Editor Koding (Antigravity IDE / VS Code)
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

# 8. Tampilkan Ringkasan Selesai
echo ""
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}   🎉 PROJECT BERHASIL DISIAPKAN!                    ${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo -e "Nama Project : ${BOLD}$REPO_NAME${NC}"
echo -e "Lokasi Host  : ${YELLOW}$TARGET_DIR${NC}"
echo -e "Versi PHP    : ${CYAN}PHP $PHP_VER${NC}"
echo -e "Akses Web    : ${CYAN}http://localhost:$PORT/$REPO_NAME${NC}"
echo -e "phpMyAdmin   : ${CYAN}http://localhost:8888${NC}"
echo -e "Database     : MariaDB (Host: ${CYAN}database${NC}, DB: ${CYAN}$DB_SAFE_NAME${NC}, User: ${CYAN}root${NC}, Pass: ${CYAN}tiger${NC})"
echo ""

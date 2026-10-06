#!/bin/bash
# ==============================================================================
# EGOV-LAMP PROJECT DELETER
# Hapus Project Lokal & Database MariaDB Terkait
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

PROJECT_NAME="$1"

echo ""
echo -e "${RED}=====================================================${NC}"
echo -e "${RED}   🗑️  HAPUS PROJECT LOKAL & DATABASE (EGOV-LAMP)     ${NC}"
echo -e "${RED}=====================================================${NC}"
echo -e "Direktori Host : ${YELLOW}$ROOT_DIR/www/${NC}"
echo ""

# Reserved folders
RESERVED=("." ".." "cli" "config" "data" "logs" "docker-compose.yml" "README.md" "test_db.php" "test_db_pdo.php" "phpinfo.php" "index.php")

# If project name not passed, list available local projects
if [ -z "$PROJECT_NAME" ]; then
    echo -e "${CYAN}Daftar project lokal yang tersedia di www/:${NC}"
    PROJECTS=()
    for d in "$ROOT_DIR/www"/*; do
        if [ -d "$d" ]; then
            bname=$(basename "$d")
            # Skip reserved
            skip=0
            for r in "${RESERVED[@]}"; do
                if [ "$bname" == "$r" ]; then
                    skip=1
                    break
                fi
            done
            if [ $skip -eq 0 ]; then
                PROJECTS+=("$bname")
            fi
        fi
    done

    if [ ${#PROJECTS[@]} -eq 0 ]; then
        echo -e "${YELLOW}Tidak ada project lokal yang dapat dihapus.${NC}"
        exit 0
    fi

    for i in "${!PROJECTS[@]}"; do
        echo -e "  $((i+1))) ${PROJECTS[$i]}"
    done
    echo ""
    read -p "Pilih nomor project atau ketik nama project: " INPUT_CHOICE

    if [[ "$INPUT_CHOICE" =~ ^[0-9]+$ ]] && [ "$INPUT_CHOICE" -ge 1 ] && [ "$INPUT_CHOICE" -le "${#PROJECTS[@]}" ]; then
        PROJECT_NAME="${PROJECTS[$((INPUT_CHOICE-1))]}"
    else
        PROJECT_NAME="$INPUT_CHOICE"
    fi
fi

# Sanitasi nama project
PROJECT_NAME=$(echo "$PROJECT_NAME" | sed 's/[^a-zA-Z0-9_\-]//g')
if [ -z "$PROJECT_NAME" ]; then
    echo -e "${RED}Nama project tidak valid!${NC}"
    exit 1
fi

TARGET_DIR="$ROOT_DIR/www/$PROJECT_NAME"
if [ ! -d "$TARGET_DIR" ]; then
    echo -e "${RED}Error: Folder www/$PROJECT_NAME tidak ditemukan!${NC}"
    exit 1
fi

# Pastikan bukan folder reserved
for r in "${RESERVED[@]}"; do
    if [ "$PROJECT_NAME" == "$r" ]; then
        echo -e "${RED}Error: '$PROJECT_NAME' adalah direktori sistem yang dilindungi dan tidak dapat dihapus!${NC}"
        exit 1
    fi
done

SAFE_DB=$(echo "$PROJECT_NAME" | sed 's/[^a-zA-Z0-9_]/_/g')

echo ""
echo -e "${YELLOW}PERINGATAN: Anda akan menghapus folder www/$PROJECT_NAME secara permanen.${NC}"
read -p "Apakah Anda juga ingin menghapus database MariaDB lokal '$SAFE_DB'? [y/N]: " DROP_DB_CHOICE
DROP_DB_CHOICE=$(echo "$DROP_DB_CHOICE" | tr '[:upper:]' '[:lower:]')

echo ""
echo -e "${RED}Untuk mengonfirmasi penghapusan permanen, ketik nama project persis '${BOLD}$PROJECT_NAME${NC}${RED}':${NC}"
read -p "> " CONFIRM_NAME

if [ "$CONFIRM_NAME" != "$PROJECT_NAME" ]; then
    echo -e "${YELLOW}Konfirmasi tidak cocok. Penghapusan dibatalkan.${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Sedang menghapus...${NC}"

# Drop database jika dipilih
if [ "$DROP_DB_CHOICE" == "y" ] || [ "$DROP_DB_CHOICE" == "yes" ]; then
    echo -e "• Menghapus database MariaDB '${CYAN}$SAFE_DB${NC}'..."
    DB_CONTAINER=$(docker ps --format '{{.Names}}' | grep -E 'egov-database' | head -n 1)
    if [ -n "$DB_CONTAINER" ]; then
        docker exec "$DB_CONTAINER" mysql -uroot -ptiger -e "DROP DATABASE IF EXISTS \`$SAFE_DB\`; DROP DATABASE IF EXISTS \`${SAFE_DB}_database\`;" 2>/dev/null
        echo -e "${GREEN}✔ Database '$SAFE_DB' berhasil dihapus.${NC}"
    else
        echo -e "${YELLOW}⚠ Container database sedang tidak aktif, melewati drop database.${NC}"
    fi
fi

# Hapus folder project
echo -e "• Menghapus folder '${CYAN}www/$PROJECT_NAME${NC}'..."
rm -rf "$TARGET_DIR"

if [ -d "$TARGET_DIR" ]; then
    # Jika gagal dengan host permission, coba via container root
    CONTAINER=$(docker ps --format '{{.Names}}' | grep -E '^egov-php' | head -n 1)
    if [ -n "$CONTAINER" ]; then
        docker exec "$CONTAINER" rm -rf "/var/www/html/$PROJECT_NAME" 2>/dev/null
    fi
fi

if [ -d "$TARGET_DIR" ]; then
    echo -e "${RED}Gagal menghapus folder www/$PROJECT_NAME (permission denied). Jalankan dengan sudo.${NC}"
    exit 1
fi

# Reset github cache agar repo muncul kembali di tab GitHub Repo jika relevan
rm -f "$ROOT_DIR/www/.github_cache.json"

echo ""
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}✔ Project '$PROJECT_NAME' berhasil dihapus!${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo ""

#!/bin/bash
# ==============================================================================
# EGOV-LAMP UPDATE MANAGER
# Cek dan perbarui egov-lamp dari GitHub repository
# ==============================================================================

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/../docker-compose.yml" ]; then
    ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
elif [ -d "/home/hendra/workspace/egov" ]; then
    ROOT_DIR="/home/hendra/workspace/egov"
else
    ROOT_DIR="$(pwd)"
fi

cd "$ROOT_DIR" || { echo -e "${RED}Direktori egov-lamp tidak ditemukan!${NC}"; exit 1; }

echo -e "${CYAN}=====================================================${NC}"
echo -e "${CYAN}   🚀   EGOV-LAMP UPDATE MANAGER                     ${NC}"
echo -e "${CYAN}=====================================================${NC}"
echo -e "Direktori : ${YELLOW}$ROOT_DIR${NC}"

CURRENT_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")

echo -e "Branch    : ${BOLD}$CURRENT_BRANCH${NC}"
echo -e "Commit    : ${BOLD}$CURRENT_COMMIT${NC}"
echo ""
echo -e "${BLUE}Memeriksa pembaruan dari GitHub (origin/$CURRENT_BRANCH)...${NC}"

git fetch origin "$CURRENT_BRANCH" 2>/dev/null

LOCAL_HASH=$(git rev-parse HEAD 2>/dev/null)
REMOTE_HASH=$(git rev-parse "origin/$CURRENT_BRANCH" 2>/dev/null)

if [ -z "$REMOTE_HASH" ]; then
    echo -e "${RED}Gagal memeriksa remote GitHub. Pastikan terhubung ke internet.${NC}"
    exit 1
fi

if [ "$LOCAL_HASH" == "$REMOTE_HASH" ]; then
    echo -e "${GREEN}✔ egov-lamp sudah versi terbaru! ($CURRENT_COMMIT)${NC}"
    
    # Pastikan file lamp_version sinkron
    if [ -f "$SCRIPT_DIR/../.git/hooks/post-merge" ]; then
        bash "$SCRIPT_DIR/../.git/hooks/post-merge"
    fi
    exit 0
fi

COMMITS_BEHIND=$(git rev-list --count HEAD.."origin/$CURRENT_BRANCH" 2>/dev/null || echo "beberapa")
echo -e "${YELLOW}🔔 Ada pembaruan tersedia! ($COMMITS_BEHIND commit baru)${NC}"
echo ""
echo -e "${BOLD}Daftar perubahan terbaru:${NC}"
git log HEAD.."origin/$CURRENT_BRANCH" --pretty=format:"  - %Cred%h%Creset %s (%Cgreen%cr%Creset by %Cblue%an%Creset)" -n 10
echo ""
echo ""

AUTO_YES=false
if [ "$1" == "-y" ] || [ "$1" == "--yes" ]; then
    AUTO_YES=true
fi

if [ "$AUTO_YES" = false ]; then
    read -p "Terapkan update sekarang (git pull)? [Y/n]: " CONFIRM_UPDATE
    CONFIRM_UPDATE=${CONFIRM_UPDATE:-Y}
    if [[ ! "$CONFIRM_UPDATE" =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Update dibatalkan.${NC}"
        exit 0
    fi
fi

echo -e "${YELLOW}Menerapkan update...${NC}"
git pull origin "$CURRENT_BRANCH"

if [ $? -eq 0 ]; then
    NEW_COMMIT=$(git rev-parse --short HEAD)
    echo ""
    echo -e "${GREEN}✔ Sukses! egov-lamp telah diperbarui ke commit $NEW_COMMIT.${NC}"
    
    # Update lamp_version cache
    if [ -f "$SCRIPT_DIR/../.git/hooks/post-merge" ]; then
        bash "$SCRIPT_DIR/../.git/hooks/post-merge"
    fi

    # Cek apakah container perlu direstart
    CHANGED_FILES=$(git diff --name-only "$LOCAL_HASH" "$REMOTE_HASH" 2>/dev/null)
    if echo "$CHANGED_FILES" | grep -qE "docker-compose\.yml|bin/|config/"; then
        echo -e "${YELLOW}Catatan: Terdapat perubahan konfigurasi container atau Dockerfile.${NC}"
        read -p "Apakah Anda ingin me-restart container sekarang? [y/N]: " RESTART_CHOICE
        if [[ "$RESTART_CHOICE" =~ ^[Yy]$ ]]; then
            echo -e "${BLUE}Restarting containers...${NC}"
            docker compose up -d
            echo -e "${GREEN}✔ Container berhasil di-restart.${NC}"
        fi
    fi
else
    echo -e "${RED}Gagal melakukan git pull. Periksa konflik lokal jika ada.${NC}"
    exit 1
fi

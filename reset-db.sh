#!/usr/bin/env bash
# reset-db.sh — Reinicia por completo la base de datos de tt-nexus (SOLO PARA TEST)
#
# Elimina TODOS los registros: hace DROP + CREATE de la base y vuelve a correr
# migraciones y seeders (reutilizando setup.sh). Pensado para entornos de prueba.
#
# Uso:
#   ./reset-db.sh              # modo local (mysql/php en el host)
#   ./reset-db.sh --docker     # modo Docker (docker compose exec ...)
#   ./reset-db.sh --yes        # no pide confirmación interactiva
#   ./reset-db.sh --force      # omite los seguros de host/baseURL (NO recomendado)
#
set -euo pipefail

# ── Colores ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

ok()   { echo -e "${GREEN}  [OK]${RESET} $*"; }
info() { echo -e "${CYAN}  -->  ${RESET}$*"; }
warn() { echo -e "${YELLOW}  [!]  ${RESET}$*"; }
fail() { echo -e "${RED}  [ERR]${RESET} $*"; exit 1; }
step() { echo -e "\n${BOLD}${CYAN}==> $*${RESET}"; }

# ── Flags ──────────────────────────────────────────────────────────────────────
DOCKER=false
ASSUME_YES=false
FORCE=false
for arg in "$@"; do
    case "$arg" in
        --docker) DOCKER=true ;;
        --yes|-y) ASSUME_YES=true ;;
        --force)  FORCE=true ;;
        -h|--help)
            echo "Uso: $0 [--docker] [--yes] [--force]"
            echo "  --docker   Corre los comandos dentro del contenedor / servicio db"
            echo "  --yes,-y   No pide confirmación interactiva"
            echo "  --force    Omite los seguros de host/baseURL (peligroso)"
            exit 0 ;;
        *) fail "Argumento desconocido: $arg" ;;
    esac
done

# ── Directorio raíz del proyecto ───────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║      tt-nexus  —  Reset de BASE DE DATOS  ║"
echo "  ║          ${RED}SOLO PARA ENTORNOS DE TEST${RESET}${BOLD}       ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${RESET}"

# ── Verificaciones previas ─────────────────────────────────────────────────────
step "Verificando entorno"

[[ -f "spark" ]]   || fail "No se encontró 'spark'. Ejecuta desde la raíz del proyecto."
[[ -f ".env" ]]    || fail "No se encontró .env. No puedo determinar la base de datos objetivo."
[[ -f "setup.sh" ]]|| fail "No se encontró setup.sh (se usa para re-migrar/seedear)."

# Lee un valor de .env: primera coincidencia de 'clave = valor', sin comillas ni espacios.
env_get() {
    grep -E "^\s*$1\s*=" .env | head -n1 | cut -d'=' -f2- | sed -E "s/^[[:space:]]*//; s/[[:space:]]*$//; s/^['\"]//; s/['\"]$//"
}

CI_ENV="$(env_get 'CI_ENVIRONMENT')"
BASE_URL="$(env_get 'app\.baseURL')"
DB_HOST="$(env_get 'database\.default\.hostname')"
DB_NAME="$(env_get 'database\.default\.database')"
DB_USER="$(env_get 'database\.default\.username')"
DB_PASS="$(env_get 'database\.default\.password')"
DB_PORT="$(env_get 'database\.default\.port')"
DB_PORT="${DB_PORT:-3306}"

[[ -n "$DB_NAME" ]] || fail "No pude leer 'database.default.database' desde .env."

info "CI_ENVIRONMENT : ${CI_ENV:-<vacío>}"
info "app.baseURL    : ${BASE_URL:-<vacío>}"
info "BD objetivo    : ${BOLD}$DB_NAME${RESET} @ $DB_HOST:$DB_PORT (user: $DB_USER)"

# ── SEGURO 1: nunca en producción ──────────────────────────────────────────────
if [[ "$CI_ENV" == "production" ]]; then
    fail "CI_ENVIRONMENT=production. ABORTADO: este script jamás debe correr en producción."
fi

# ── SEGURO 2: solo hosts locales (salvo --force) ───────────────────────────────
case "$DB_HOST" in
    db|localhost|127.0.0.1|::1|"") LOCAL_HOST=true ;;
    *) LOCAL_HOST=false ;;
esac
if ! $LOCAL_HOST; then
    if $FORCE; then
        warn "Host de BD '$DB_HOST' NO es local, pero --force fue usado. Continuando..."
    else
        fail "Host de BD '$DB_HOST' no parece local. Si de verdad es un entorno de test remoto, usa --force."
    fi
fi

# ── SEGURO 3: baseURL debe apuntar a localhost (salvo --force) ─────────────────
if [[ -n "$BASE_URL" && "$BASE_URL" != *localhost* && "$BASE_URL" != *127.0.0.1* ]]; then
    if $FORCE; then
        warn "app.baseURL '$BASE_URL' no es localhost, pero --force fue usado. Continuando..."
    else
        fail "app.baseURL '$BASE_URL' no es localhost. Parece un entorno real. Usa --force si estás seguro."
    fi
fi

# ── Wrapper mysql: local o Docker ──────────────────────────────────────────────
mysql_exec() {
    # $1 = SQL a ejecutar
    if $DOCKER; then
        docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" -e "$1"
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "$1"
    fi
}

if $DOCKER; then
    command -v docker &>/dev/null || fail "Docker no está disponible en el PATH."
    if ! docker compose ps --status running | grep -q "tt-apps"; then
        info "Levantando contenedores..."
        docker compose up -d
        for i in {1..20}; do
            if docker compose exec -T db mysqladmin ping -h localhost -u"$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; then
                break
            fi
            echo -n "."; sleep 2
        done
        echo ""
    fi
else
    command -v mysql &>/dev/null || fail "El cliente 'mysql' no está en el PATH (usa --docker o instálalo)."
fi

# ── Confirmación ───────────────────────────────────────────────────────────────
if ! $ASSUME_YES; then
    echo ""
    warn "Esto ${BOLD}ELIMINARÁ POR COMPLETO${RESET}${YELLOW} la base de datos '${BOLD}$DB_NAME${RESET}${YELLOW}' y la recreará vacía."
    read -rp "  Escribe el nombre de la base de datos para confirmar: " CONFIRM
    [[ "$CONFIRM" == "$DB_NAME" ]] || fail "El nombre no coincide ('$CONFIRM' != '$DB_NAME'). Abortado."
fi

# ── DROP + CREATE ──────────────────────────────────────────────────────────────
step "Reiniciando la base de datos"
info "DROP DATABASE \`$DB_NAME\`"
mysql_exec "DROP DATABASE IF EXISTS \`$DB_NAME\`;" || fail "No se pudo eliminar la base de datos."
info "CREATE DATABASE \`$DB_NAME\`"
mysql_exec "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || fail "No se pudo recrear la base de datos."
ok "Base de datos '$DB_NAME' recreada vacía"

# ── Re-migrar y re-seedear reutilizando setup.sh ───────────────────────────────
step "Re-aplicando migraciones y seeders (setup.sh)"
if $DOCKER; then
    ./setup.sh --docker
else
    ./setup.sh
fi

echo -e "\n${BOLD}${GREEN}  Reset completo.${RESET} La base '$DB_NAME' quedó limpia y con datos semilla.\n"

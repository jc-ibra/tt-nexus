#!/usr/bin/env bash
# setup.sh — Levanta migraciones y seeders iniciales de tt-nexus
#
# Uso:
#   ./setup.sh            # modo local (PHP en el host)
#   ./setup.sh --docker   # modo Docker (docker compose exec app ...)
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
for arg in "$@"; do
    case "$arg" in
        --docker) DOCKER=true ;;
        -h|--help)
            echo "Uso: $0 [--docker]"
            echo "  (sin flags)  Corre php spark directamente en el host"
            echo "  --docker     Corre los comandos dentro del contenedor tt-apps"
            exit 0 ;;
        *) fail "Argumento desconocido: $arg" ;;
    esac
done

# ── Wrapper spark: local o Docker ──────────────────────────────────────────────
spark() {
    if $DOCKER; then
        docker compose exec app php spark "$@"
    else
        php spark "$@"
    fi
}

# ── Directorio raíz del proyecto ───────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║        tt-nexus  —  Setup inicial        ║"
if $DOCKER; then
echo "  ║              modo: Docker                ║"
else
echo "  ║               modo: local                ║"
fi
echo "  ╚══════════════════════════════════════════╝"
echo -e "${RESET}"

# ── Verificaciones previas ─────────────────────────────────────────────────────
step "Verificando entorno"

[[ -f "spark" ]] || fail "No se encontró 'spark'. Ejecuta este script desde la raíz del proyecto."

if $DOCKER; then
    command -v docker &>/dev/null || fail "Docker no está disponible en el PATH."

    # Levantar contenedores si no están corriendo
    if ! docker compose ps --status running | grep -q "tt-apps"; then
        info "Levantando contenedores..."
        docker compose up -d --build
        info "Esperando que la base de datos esté lista..."
        # El healthcheck del servicio db ya garantiza esto, pero esperamos unos segundos extra
        for i in {1..20}; do
            if docker compose exec db mysqladmin ping -h localhost -u root -proot --silent 2>/dev/null; then
                break
            fi
            echo -n "."
            sleep 2
        done
        echo ""
        ok "Base de datos lista"
    else
        ok "Contenedores ya están corriendo"
    fi

    CONTAINER_PHP=$(docker compose exec app php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "?")
    info "PHP en contenedor: $CONTAINER_PHP"
else
    command -v php &>/dev/null || fail "PHP no está disponible en el PATH."
    PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    info "PHP version: $PHP_VER"
fi

# ── .env ───────────────────────────────────────────────────────────────────────
if [[ ! -f ".env" ]]; then
    if [[ -f "env" ]]; then
        warn "No existe .env — copiando desde 'env'..."
        cp env .env
        if $DOCKER; then
            warn "Asegúrate de que .env tenga DB_HOSTNAME=db (nombre del servicio Docker)."
        fi
        warn "Edita .env con tus credenciales de BD y SMTP antes de continuar."
        read -rp "  Presiona ENTER cuando hayas configurado .env... "
    else
        fail "No se encontró .env ni 'env'. Crea tu archivo .env antes de continuar."
    fi
fi
ok ".env presente"

# En modo Docker, advertir si DB_HOSTNAME no apunta al servicio correcto
if $DOCKER && [[ -f ".env" ]]; then
    DB_HOST_VAL=$(grep -E '^DB_HOSTNAME\s*=' .env | cut -d'=' -f2 | tr -d ' ' || true)
    if [[ -n "$DB_HOST_VAL" && "$DB_HOST_VAL" != "db" ]]; then
        warn "DB_HOSTNAME='$DB_HOST_VAL' en .env — dentro de Docker debe ser 'db' (nombre del servicio)."
    fi
fi

# ── Dependencias de Composer (solo modo local) ─────────────────────────────────
if ! $DOCKER; then
    step "Dependencias de Composer"
    if [[ ! -d "vendor" ]]; then
        info "Instalando dependencias..."
        composer install --no-interaction --prefer-dist
    else
        ok "vendor/ ya existe"
    fi
fi

# ── Módulos y sus namespaces ───────────────────────────────────────────────────
declare -a MODULES=(
    "App\\Modules\\Core"
    "App\\Modules\\Communications"
    "App\\Modules\\Employees"
    "App\\Modules\\KPIsOperativos"
    "App\\Modules\\Mailboxes"
    "App\\Modules\\Provisioning"
)

# ── Migraciones ────────────────────────────────────────────────────────────────
step "Migraciones"

for NS in "${MODULES[@]}"; do
    MODULE_SHORT="${NS##*\\}"
    info "Migrando: $MODULE_SHORT"
    if spark migrate -n "$NS" --no-interaction 2>&1; then
        ok "$MODULE_SHORT"
    else
        warn "Falló o no hay migraciones para $MODULE_SHORT (puede ser normal si el namespace no existe aún)"
    fi
done

# ── Seeders ────────────────────────────────────────────────────────────────────
step "Seeders"

run_seeder() {
    local CLASS="$1"
    local LABEL="$2"
    info "Seeder: $LABEL"
    if spark db:seed "$CLASS" 2>&1; then
        ok "$LABEL"
    else
        warn "Falló el seeder $LABEL (puede ser que ya existan los datos)"
    fi
}

# Core: roles, usuario admin y módulo Communications
run_seeder "App\Database\Seeds\CoreSeeder" "CoreSeeder"

# Employees
run_seeder "App\Modules\Employees\Database\Seeders\EmployeesModuleSeeder"          "EmployeesModuleSeeder"
run_seeder "App\Modules\Employees\Database\Seeders\EmployeeAreasSeeder"            "EmployeeAreasSeeder"
run_seeder "App\Modules\Employees\Database\Seeders\EmployeeDepartmentsSeeder"      "EmployeeDepartmentsSeeder"
run_seeder "App\Modules\Employees\Database\Seeders\EmployeePositionsSeeder"        "EmployeePositionsSeeder"

# KPIsOperativos
run_seeder "App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder" "KPIsOperativosModuleSeeder"
run_seeder "App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder"    "GlpiCoordinatorsSeeder"

# Mailboxes
run_seeder "App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder"          "MailboxesModuleSeeder"

# Provisioning
run_seeder "App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder"   "ProvisioningModuleSeeder"

# ── Estado final de migraciones ────────────────────────────────────────────────
step "Estado de migraciones"
spark migrate:status 2>/dev/null || true

# ── Listo ──────────────────────────────────────────────────────────────────────
echo -e "\n${BOLD}${GREEN}  Setup completo.${RESET}"
if $DOCKER; then
    echo -e "  App disponible en:  ${BOLD}http://localhost:8080${RESET}"
    echo -e "  phpMyAdmin en:      ${BOLD}http://localhost:8081${RESET}\n"
else
    echo -e "  Inicia el servidor con:  ${BOLD}php spark serve${RESET}\n"
fi

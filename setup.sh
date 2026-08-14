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

    # Levantar contenedores si no están corriendo.
    # Detectamos por el SERVICIO 'app' (no por nombre de contenedor, que cambia
    # entre proyectos) para no reconstruir innecesariamente en cada corrida.
    if [[ -z "$(docker compose ps --status running --services 2>/dev/null | grep -x app)" ]]; then
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

# ── Dependencias de Composer ───────────────────────────────────────────────────
# En Docker, vendor/ es un VOLUMEN nombrado (vendor_data) que persiste entre
# rebuilds y NO se actualiza con el composer del host. Por eso corremos
# `composer install` DENTRO del contenedor: así una dependencia nueva agregada al
# composer.lock (p.ej. webklex/php-imap) llega al volumen sin instalarla a mano.
# Es idempotente: si ya está todo, es un no-op rápido.
step "Dependencias de Composer"
if $DOCKER; then
    if docker compose exec -T app sh -lc 'command -v composer >/dev/null 2>&1'; then
        info "Instalando dependencias dentro del contenedor..."
        docker compose exec -T app composer install --no-interaction --prefer-dist \
            && ok "Dependencias sincronizadas en el contenedor" \
            || warn "composer install reportó avisos (revisa arriba)."
    else
        warn "composer no está disponible en el contenedor; se omite (asume vendor/ del build)."
    fi
else
    if [[ ! -d "vendor" ]]; then
        info "Instalando dependencias..."
        composer install --no-interaction --prefer-dist
    else
        info "vendor/ ya existe — sincronizando con composer.lock..."
        composer install --no-interaction --prefer-dist && ok "Dependencias sincronizadas" \
            || warn "composer install reportó avisos (revisa arriba)."
    fi
fi

# ── Baseline (reconciliación de historial) ─────────────────────────────────────
# Antes de migrar, reconciliamos la tabla `migrations` con el esquema real: si una
# BD ya tiene tablas/columnas creadas a mano (típico de un levantamiento manual en
# staging/prod) pero sin el registro de migración, `migrate` intentaría recrearlas
# y fallaría con "already exists". `db:baseline` marca esas migraciones como
# aplicadas SÓLO si verifica que su efecto ya existe. Es seguro e idempotente:
# en una BD limpia no hace nada (las tablas aún no existen).
step "Baseline (reconciliación de historial)"
spark db:baseline --no-interaction || warn "db:baseline reportó avisos (revisa arriba); continúo con migrate."

# ── Migraciones ────────────────────────────────────────────────────────────────
# Usamos `migrate --all`: auto-descubre TODOS los namespaces de módulos
# registrados en app/Config/Autoload.php y aplica las migraciones en orden
# cronológico GLOBAL (no por-módulo). Esto es clave porque hay FKs entre módulos
# (p.ej. KPIsOperativos referencia tablas creadas por su propio orden temporal)
# y evita el fallo de "olvidé agregar el módulo nuevo a la lista de setup".
#
# Un error de migración es FATAL: preferimos abortar y verlo ahora que descubrir
# una tabla faltante semanas después usando el sistema.
step "Migraciones"

info "Aplicando todas las migraciones (migrate --all)"
if spark migrate --all --no-interaction; then
    ok "Migraciones aplicadas"
else
    fail "Falló la migración. Revisa el error de arriba ANTES de continuar (no se sembrarán datos)."
fi

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

# AgentKpis
run_seeder "App\Modules\AgentKpis\Database\Seeders\AgentKpisModuleSeeder"                  "AgentKpisModuleSeeder"

# HelpdeskSupervisor
run_seeder "App\Modules\HelpdeskSupervisor\Database\Seeders\HelpdeskSupervisorModuleSeeder" "HelpdeskSupervisorModuleSeeder"
run_seeder "App\Modules\HelpdeskSupervisor\Database\Seeders\CoordinatorMapSeeder"          "CoordinatorMapSeeder"

# KPIsOperativos
run_seeder "App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder" "KPIsOperativosModuleSeeder"
run_seeder "App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder"    "GlpiCoordinatorsSeeder"

# Mailboxes
run_seeder "App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder"          "MailboxesModuleSeeder"

# Provisioning
run_seeder "App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder"   "ProvisioningModuleSeeder"
run_seeder "App\Modules\Provisioning\Database\Seeders\MsLicensesSeeder"           "MsLicensesSeeder"

# ServiceDesk
run_seeder "App\Modules\ServiceDesk\Database\Seeders\ServiceDeskModuleSeeder"     "ServiceDeskModuleSeeder"

# MailDispatch
run_seeder "App\Modules\MailDispatch\Database\Seeders\MailDispatchModuleSeeder"   "MailDispatchModuleSeeder"

# TechBot
run_seeder "App\Modules\TechBot\Database\Seeders\TechBotModuleSeeder"             "TechBotModuleSeeder"

# ── Tareas de datos posteriores a la migración ─────────────────────────────────
# Una migración crea la estructura; algunas funciones necesitan además rellenar
# datos derivados de lo ya almacenado. Idempotentes: procesan sólo lo pendiente,
# así que en un setup rutinario no hacen nada.
step "Tareas posteriores a la migración"

# MailDispatch: texto plano buscable del cuerpo de los mensajes. Los correos
# nuevos lo traen desde la ingesta; esto cubre los ya almacenados.
info "Texto buscable de Despacho de Correo"
spark maildispatch:backfill-body-text || warn "El backfill reportó avisos (revisa arriba); puedes reintentarlo cuando quieras."

# ── Verificación de esquema ────────────────────────────────────────────────────
# Confirma que CADA tabla declarada por las migraciones exista físicamente. Es la
# red de seguridad contra el problema recurrente de "tablas faltantes": si algo
# no se creó, el setup falla AQUÍ en vez de que lo descubras usando el sistema.
step "Verificación de esquema"
if ! spark db:verify-schema; then
    fail "Hay tablas faltantes (ver arriba). El setup NO está completo."
fi

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

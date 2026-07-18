#!/usr/bin/env bash
# reset-db.sh — Reinicia la base de datos de tt-nexus (SOLO PARA TEST)
#
# Tiene DOS modos (ver documentación completa en RESET-DB.md, solo local):
#
#   (por defecto) HARD reset  -> Borra TODO: hace DROP + CREATE de la base y vuelve
#                                a correr migraciones + seeders (reutilizando setup.sh).
#                                Se pierde login, RBAC, credenciales y configuración.
#
#   --soft        Reset SUAVE  -> Vacía (TRUNCATE) solo los datos transaccionales /
#                                telemetría / bitácoras. CONSERVA las tablas de Core
#                                (core_*, ci_sessions, migrations) y las de settings /
#                                credenciales / mapeos de configuración. No toca el
#                                esquema (no migra); re-ejecuta los seeders idempotentes.
#
# Uso:
#   ./reset-db.sh              # HARD reset, modo local (mysql/php en el host)
#   ./reset-db.sh --soft       # reset SUAVE (conserva Core + settings + configs)
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
MODE="hard"   # hard = DROP+CREATE (por defecto);  soft = TRUNCATE conservando config
for arg in "$@"; do
    case "$arg" in
        --docker) DOCKER=true ;;
        --soft)   MODE="soft" ;;
        --hard)   MODE="hard" ;;
        --yes|-y) ASSUME_YES=true ;;
        --force)  FORCE=true ;;
        -h|--help)
            echo "Uso: $0 [--soft|--hard] [--docker] [--yes] [--force]"
            echo "  --soft     Reset SUAVE: vacía datos pero CONSERVA Core + settings + configs"
            echo "  --hard     Reset DURO (por defecto): DROP + CREATE de toda la base"
            echo "  --docker   Corre los comandos dentro del contenedor / servicio db"
            echo "  --yes,-y   No pide confirmación interactiva"
            echo "  --force    Omite los seguros de host/baseURL (peligroso)"
            exit 0 ;;
        *) fail "Argumento desconocido: $arg" ;;
    esac
done

# ── Tablas de settings/credenciales/config que el reset SUAVE conserva ───────────
# (además de todas las tablas de Core: core_*, ci_sessions y migrations).
# Debe mantenerse sincronizado con $PRESERVE_SETTINGS en public/reset-db.php.
# Ver RESET-DB.md para la justificación de cada tabla (solo vive en local).
PRESERVE_SETTINGS=(
    # Provisioning
    provisioning_settings
    provisioning_system_credentials
    provisioning_systems
    provisioning_glpi_catalog_prefs
    # Mailboxes
    mailboxes_settings
    # Service Desk: settings (importador + widget + IA + backlog), mapeos de categorías
    servicedesk_settings
    servicedesk_category_map
    servicedesk_backlog_areas
)

# ── Directorios de archivos generados que el reset LIMPIA (en ambos modos) ────────
# El importador de Service Desk guarda en disco (no en la BD) las bitácoras, los
# Excel de origen/resultado y las plantillas temporales. El nombre del archivo se
# deriva del id del import (p. ej. writable/servicedesk/logs/import_2.log). Tras un
# reset, el AUTO_INCREMENT de servicedesk_imports vuelve a 1, así que un import nuevo
# REUTILIZA un archivo viejo y, como la bitácora se escribe con FILE_APPEND y nunca
# se trunca, concatena corridas distintas en un mismo log. Por eso hay que borrarlos.
# Rutas relativas a la raíz del proyecto. Sincronizar con $PURGE_DIRS en public/reset-db.php.
PURGE_DIRS=(
    writable/servicedesk/logs
    writable/servicedesk/uploads
    writable/servicedesk/tmp
)

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
    # $1 = SQL a ejecutar (contra el servidor; sin base seleccionada)
    if $DOCKER; then
        docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" -e "$1"
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "$1"
    fi
}

# Consulta en modo batch (sin encabezados/formato) contra la base objetivo.
mysql_query() {
    # $1 = SQL; imprime una fila por línea, sin decoración.
    if $DOCKER; then
        docker compose exec -T db mysql -N -B -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -N -B "$DB_NAME" -e "$1"
    fi
}

# ── Wrapper spark: local o Docker (para re-seedear en el reset suave) ────────────
spark() {
    if $DOCKER; then
        docker compose exec app php spark "$@"
    else
        php spark "$@"
    fi
}

run_seeder() {
    local CLASS="$1" LABEL="${2:-$1}"
    info "Seeder: $LABEL"
    if spark db:seed "$CLASS" >/dev/null 2>&1; then
        ok "$LABEL"
    else
        warn "Falló el seeder $LABEL (puede ser que ya existan los datos)"
    fi
}

# Borra los directorios de archivos generados (bitácoras / Excel del importador de
# Service Desk). El siguiente import los recrea bajo demanda (ensureDir). Se corre en
# ambos modos: servicedesk_imports se vacía tanto en hard como en soft reset.
purge_generated_files() {
    step "Limpiando archivos generados del importador (writable/)"
    local dir count=0
    for dir in "${PURGE_DIRS[@]}"; do
        if $DOCKER; then
            docker compose exec -T app rm -rf "$dir" >/dev/null 2>&1 || true
            info "purgado (docker): $dir"; count=$((count+1))
        elif [[ -d "$dir" ]]; then
            rm -rf "${dir:?}"
            info "purgado: $dir"; count=$((count+1))
        fi
    done
    ok "$count directorio(s) de archivos generados limpiado(s)."
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

info "Modo           : ${BOLD}$( [[ "$MODE" == soft ]] && echo 'SUAVE (conserva Core + settings + configs)' || echo 'DURO (DROP + CREATE de toda la base)' )${RESET}"

# ── Confirmación ───────────────────────────────────────────────────────────────
if ! $ASSUME_YES; then
    echo ""
    if [[ "$MODE" == soft ]]; then
        warn "Esto ${BOLD}VACIARÁ los datos${RESET}${YELLOW} de '${BOLD}$DB_NAME${RESET}${YELLOW}' pero CONSERVARÁ Core, settings y configuraciones."
    else
        warn "Esto ${BOLD}ELIMINARÁ POR COMPLETO${RESET}${YELLOW} la base de datos '${BOLD}$DB_NAME${RESET}${YELLOW}' y la recreará vacía."
    fi
    read -rp "  Escribe el nombre de la base de datos para confirmar: " CONFIRM
    [[ "$CONFIRM" == "$DB_NAME" ]] || fail "El nombre no coincide ('$CONFIRM' != '$DB_NAME'). Abortado."
fi

# Limpia los archivos generados en disco en ambos modos (evita bitácoras huérfanas
# que se reusan al reiniciar el AUTO_INCREMENT de servicedesk_imports).
purge_generated_files

if [[ "$MODE" == soft ]]; then
    # ── RESET SUAVE: TRUNCATE de datos, conservando Core + settings/config ──────
    step "Reset suave — vaciando datos (conservando Core, settings y configuraciones)"

    # Conjunto a conservar: migrations, ci_sessions, cualquier tabla core_* y la
    # lista explícita de settings/config.
    declare -A PRESERVE=( [migrations]=1 [ci_sessions]=1 )
    for t in "${PRESERVE_SETTINGS[@]}"; do PRESERVE["$t"]=1; done

    TABLES="$(mysql_query 'SHOW TABLES;')" || fail "No pude listar las tablas de '$DB_NAME'."
    [[ -n "$TABLES" ]] || fail "La base '$DB_NAME' no tiene tablas. ¿Quisiste un --hard reset?"

    TRUNC_SQL="SET FOREIGN_KEY_CHECKS = 0;"
    truncated=0; kept=0; kept_list=""
    while IFS= read -r t; do
        [[ -z "$t" ]] && continue
        if [[ -n "${PRESERVE[$t]:-}" || "$t" == core_* ]]; then
            kept=$((kept+1)); kept_list+="${kept_list:+, }$t"; continue
        fi
        TRUNC_SQL+="TRUNCATE TABLE \`$t\`;"
        info "TRUNCATE $t"
        truncated=$((truncated+1))
    done <<< "$TABLES"
    TRUNC_SQL+="SET FOREIGN_KEY_CHECKS = 1;"

    if (( truncated > 0 )); then
        mysql_query "$TRUNC_SQL" || fail "Falló el vaciado de tablas."
    fi
    ok "$truncated tabla(s) vaciada(s)."
    info "Conservadas ($kept): $kept_list"

    # Re-seed idempotente: restaura catálogos por defecto y el registro módulos/roles.
    # Mismo orden que setup.sh (no migramos: el esquema queda intacto).
    step "Re-ejecutando seeders (restaurar catálogos por defecto)"
    run_seeder "App\Database\Seeds\CoreSeeder"                                         "CoreSeeder"
    run_seeder "App\Modules\Employees\Database\Seeders\EmployeesModuleSeeder"          "EmployeesModuleSeeder"
    run_seeder "App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder" "KPIsOperativosModuleSeeder"
    run_seeder "App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder"    "GlpiCoordinatorsSeeder"
    run_seeder "App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder"          "MailboxesModuleSeeder"
    run_seeder "App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder"    "ProvisioningModuleSeeder"
    run_seeder "App\Modules\Provisioning\Database\Seeders\MsLicensesSeeder"            "MsLicensesSeeder"
    run_seeder "App\Modules\ServiceDesk\Database\Seeders\ServiceDeskModuleSeeder"      "ServiceDeskModuleSeeder"

    echo -e "\n${BOLD}${GREEN}  Reset suave completo.${RESET} Se conservaron Core, settings y configuraciones.\n"
else
    # ── HARD RESET: DROP + CREATE, luego re-migrar + re-seedear vía setup.sh ─────
    step "Reiniciando la base de datos (hard reset)"
    info "DROP DATABASE \`$DB_NAME\`"
    mysql_exec "DROP DATABASE IF EXISTS \`$DB_NAME\`;" || fail "No se pudo eliminar la base de datos."
    info "CREATE DATABASE \`$DB_NAME\`"
    mysql_exec "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || fail "No se pudo recrear la base de datos."
    ok "Base de datos '$DB_NAME' recreada vacía"

    step "Re-aplicando migraciones y seeders (setup.sh)"
    if $DOCKER; then
        ./setup.sh --docker
    else
        ./setup.sh
    fi

    echo -e "\n${BOLD}${GREEN}  Reset completo.${RESET} La base '$DB_NAME' quedó limpia y con datos semilla.\n"
fi

#!/usr/bin/env bash
# init.sh — First-time database initialization for tt-nexus.
# Runs all migrations and seeds inside the running Docker app container.
# Safe to re-run: all seeders are idempotent.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

APP_CONTAINER="tt-nexus"

echo -e "${BLUE}=== TT-Nexus — Database Init ===${NC}"
echo ""

# ---------------------------------------------------------------
# 1. Validate .env exists
# ---------------------------------------------------------------
if [ ! -f ".env" ]; then
    echo -e "${RED}ERROR: .env not found. Copy 'env' to '.env' and fill in the required values.${NC}"
    exit 1
fi

# ---------------------------------------------------------------
# 2. Validate required credentials are set (and non-empty)
# ---------------------------------------------------------------
get_env_value() {
    grep -E "^${1}\s*=" .env | head -1 | sed 's/^[^=]*=\s*//' | tr -d '"' | tr -d "'"
}

ADMIN_EMAIL=$(get_env_value "ADMIN_EMAIL")
ADMIN_PASSWORD=$(get_env_value "ADMIN_PASSWORD")

if [ -z "$ADMIN_EMAIL" ]; then
    echo -e "${RED}ERROR: ADMIN_EMAIL is not set in .env — add it before running this script.${NC}"
    exit 1
fi

if [ -z "$ADMIN_PASSWORD" ]; then
    echo -e "${RED}ERROR: ADMIN_PASSWORD is not set in .env — add it before running this script.${NC}"
    exit 1
fi

if [ "$ADMIN_PASSWORD" = "changeme123" ]; then
    echo -e "${YELLOW}WARNING: ADMIN_PASSWORD is still 'changeme123'. Change it in .env before deploying to production.${NC}"
    echo ""
fi

echo -e "  Admin email : ${ADMIN_EMAIL}"
echo ""

# ---------------------------------------------------------------
# 3. Confirm Docker container is running
# ---------------------------------------------------------------
if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
    echo -e "${RED}ERROR: Container '${APP_CONTAINER}' is not running.${NC}"
    echo "  Run: docker compose up -d"
    exit 1
fi

echo -e "${GREEN}Container '${APP_CONTAINER}' is running.${NC}"
echo ""

# ---------------------------------------------------------------
# 4. Run migrations — one namespace at a time
# ---------------------------------------------------------------
echo -e "${BLUE}--- Migrations ---${NC}"

NAMESPACES=(
    "App\Modules\Core"
    "App\Modules\Communications"
    "App\Modules\Employees"
    "App\Modules\KPIsOperativos"
    "App\Modules\Mailboxes"
    "App\Modules\Provisioning"
)

for NS in "${NAMESPACES[@]}"; do
    echo "  Migrating: ${NS}"
    docker exec "$APP_CONTAINER" php spark migrate -n "$NS"
done

echo ""

# ---------------------------------------------------------------
# 5. Seed initial data
# ---------------------------------------------------------------
echo -e "${BLUE}--- Seeders ---${NC}"
docker exec "$APP_CONTAINER" php spark db:seed AppSeeder

echo ""
echo -e "${GREEN}=== Init complete! ===${NC}"
echo "  App:        http://localhost:8080"
echo "  phpMyAdmin: http://localhost:8081"
echo "  Login with: ${ADMIN_EMAIL}"

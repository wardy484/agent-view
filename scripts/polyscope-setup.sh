#!/usr/bin/env bash
# polyscope-setup.sh — provision a Polyscope-cloned workspace.
#
# Usage: ./scripts/polyscope-setup.sh <folder-slug>
#
# Polyscope clones the repo into its own working directory and runs this script
# from the repo root. Unlike scripts/worktree-bootstrap.sh (which assumes a git
# worktree under ../nexus-ui-worktrees/), this script operates in-place and only
# does what Polyscope does NOT already do: provision the per-workspace Postgres
# DB, write the .env, install deps, key:generate, migrate, and seed.
#
# The dev server is intentionally NOT started here — polyscope.json handles that
# via a `run` script with `autostart: true`.
#
# Idempotent — safe to re-run.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLATFORM="$(uname -s)"
HOST_SERVICES_COMPOSE="${SCRIPT_DIR}/../infra/host-services/compose.yaml"
FOLDER="${1:-${PWD##*/}}"
SLUG="$(printf '%s' "$FOLDER" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
DB_NAME="nexus_${SLUG//-/_}"
VITE_PORT=$((5173 + $(printf '%s' "$SLUG" | cksum | cut -d' ' -f1) % 127))
APP_PORT=""

echo "==> Polyscope setup for '$FOLDER' (slug: $SLUG)"
echo "    db:   $DB_NAME"
echo "    vite: $VITE_PORT"

write_env_var() {
  local key="$1"
  local value="$2"
  python3 - "$key" "$value" <<'PYENV'
import pathlib
import re
import sys

key, value = sys.argv[1], sys.argv[2]
env_path = pathlib.Path(".env")
text = env_path.read_text()
pattern = rf"^{re.escape(key)}=.*$"
replacement = f"{key}={value}"
if re.search(pattern, text, flags=re.MULTILINE):
    text = re.sub(pattern, replacement, text, flags=re.MULTILINE)
else:
    text += f"\n{replacement}\n"
env_path.write_text(text)
PYENV
}

linux_host_gateway() {
  docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}' 2>/dev/null || echo "172.17.0.1"
}

linux_ensure_host_services() {
  if ! docker compose -f "$HOST_SERVICES_COMPOSE" ps --status running --format '{{.Service}}' | grep -qE '^(pgsql|redis)$'; then
    echo "--> starting host services stack (idempotent)"
    "$SCRIPT_DIR/host-services.sh" up
  else
    echo "--> host services stack already running"
  fi
}

linux_ensure_db() {
  local exists
  exists="$(docker compose -f "$HOST_SERVICES_COMPOSE" exec -T pgsql \
    psql -U nexus -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" || true)"
  if [[ "$exists" == "1" ]]; then
    echo "--> database $DB_NAME already exists in shared Postgres"
  else
    echo "--> creating database $DB_NAME in shared Postgres"
    docker compose -f "$HOST_SERVICES_COMPOSE" exec -T pgsql createdb -U nexus -w "$DB_NAME"
  fi
}

mac_ensure_db() {
  if psql -h 127.0.0.1 -U postgres -p 5432 -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx "$DB_NAME"; then
    echo "--> database $DB_NAME already exists"
  else
    echo "--> creating database $DB_NAME"
    createdb -h 127.0.0.1 -U postgres -p 5432 "$DB_NAME"
  fi
}

linux_prepare_env() {
  local gateway
  gateway="$(linux_host_gateway)"
  APP_PORT="$("$SCRIPT_DIR/assign-port.sh" "$SLUG")"
  write_env_var APP_URL "http://localhost:$APP_PORT"
  write_env_var APP_PORT "$APP_PORT"
  write_env_var DB_HOST "$gateway"
  write_env_var DB_DATABASE "$DB_NAME"
  write_env_var DB_USERNAME "nexus"
  write_env_var DB_PASSWORD "nexus"
  write_env_var REDIS_HOST "$gateway"
  write_env_var REDIS_PREFIX "nexus:$SLUG:"
  write_env_var VITE_PORT "$VITE_PORT"
  write_env_var VITE_HOST "$gateway"
  write_env_var FORWARD_DB_PORT "$((APP_PORT + 10000))"
  write_env_var FORWARD_REDIS_PORT "$((APP_PORT + 20000))"
  write_env_var APP_USER "$(id -u)"
  write_env_var WWWUSER "$(id -u)"
  write_env_var WWWGROUP "$(id -g)"
  if [[ "$(id -u)" == "0" ]]; then
    write_env_var SUPERVISOR_PHP_USER "root"
  else
    write_env_var SUPERVISOR_PHP_USER "sail"
  fi
  mkdir -p .polyscope
  printf '%s\n' "$APP_PORT" > .polyscope/preview-port
  echo "    port: $APP_PORT"
  echo "    url:  http://localhost:$APP_PORT"
}

mac_prepare_env() {
  write_env_var APP_URL "https://$SLUG.test"
  write_env_var DB_DATABASE "$DB_NAME"
  write_env_var DB_HOST "127.0.0.1"
  write_env_var DB_USERNAME "postgres"
  write_env_var DB_PASSWORD ""
  write_env_var REDIS_PREFIX "nexus:$SLUG:"
  write_env_var VITE_PORT "$VITE_PORT"
  echo "    url:  https://$SLUG.test"
}

composer_install() {
  if [[ "$PLATFORM" == "Linux" ]]; then
    docker run --rm \
      -u "$(id -u):$(id -g)" \
      -v "$PWD:/app" \
      -w /app \
      composer:2 \
      install
  else
    composer install
  fi
}

artisan() {
  if [[ "$PLATFORM" == "Linux" ]]; then
    ./vendor/bin/sail artisan "$@"
  else
    php artisan "$@"
  fi
}

build_assets_if_missing() {
  if [[ -f public/build/manifest.json ]]; then
    return
  fi

  if command -v pnpm >/dev/null; then
    pnpm build
  elif command -v corepack >/dev/null; then
    corepack pnpm build
  else
    npm run build
  fi
}

# 2. .env (copy from example, then patch the per-workspace values).
if [[ ! -f .env ]]; then
  cp .env.example .env
fi

case "$PLATFORM" in
  Darwin)
    mac_ensure_db
    mac_prepare_env
    ;;
  Linux)
    linux_ensure_host_services
    linux_ensure_db
    linux_prepare_env
    ;;
  *)
    echo "Unsupported platform: $PLATFORM (need Darwin or Linux)" >&2
    exit 1
    ;;
esac

# 3. Deps.
if [[ ! -d vendor ]]; then
  composer_install
fi
if [[ ! -d node_modules ]]; then
  if command -v pnpm >/dev/null; then
    pnpm install
  else
    npm install
  fi
fi

if [[ "$PLATFORM" == "Linux" ]]; then
  ./vendor/bin/sail up -d
fi

# 4. APP_KEY.
if grep -qE '^APP_KEY=base64:' .env; then
  echo "--> APP_KEY already set"
else
  artisan key:generate --ansi
fi

# 5. Migrate + seed (seeder is idempotent — wardy484@gmail.com / password
#    plus one demo snapshot per view_type).
artisan migrate --force
artisan db:seed --force

# 6. Build once so Polyscope preview works even before its dev-server runner
#    starts. The runner still starts Vite for normal development.
build_assets_if_missing

echo ""
echo "✅ Polyscope workspace ready."
echo "   Login: wardy484@gmail.com / password"
if [[ "$PLATFORM" == "Linux" ]]; then
  echo "   URL:   http://localhost:$APP_PORT"
else
  echo "   URL:   https://$SLUG.test"
fi

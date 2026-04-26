#!/usr/bin/env bash
# worktree-bootstrap.sh — idempotently provision an isolated worktree.
#
# Platform-aware via `uname -s`:
#   Darwin → Herd path (per-worktree Postgres on Herd's daemon, .test host)
#   Linux  → Sail path (shared host services + per-workspace DB inside,
#            deterministic APP_PORT via scripts/assign-port.sh)
#
# Both paths share: git worktree creation, composer/pnpm install,
# key:generate, migrate, db:seed, background pnpm dev.
#
# Usage:
#   scripts/worktree-bootstrap.sh <branch-name>
#   scripts/worktree-bootstrap.sh --help
#
# Safe to re-run; every step checks for existing state before mutating.

set -euo pipefail

PLATFORM="$(uname -s)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT_HINT="$(dirname "$SCRIPT_DIR")"
HOST_SERVICES_COMPOSE="${PROJECT_ROOT_HINT}/infra/host-services/compose.yaml"

usage() {
    cat <<EOF
Usage: $0 <branch-name>
       $0 --help

Detected platform: ${PLATFORM}
Host services compose: ${HOST_SERVICES_COMPOSE}

Bootstraps an isolated worktree for parallel work.

Darwin (Mac) path:
  - Per-worktree Postgres database on Herd's bundled Postgres (port 5432).
  - HTTPS preview at https://<slug>.test via Herd's parked sites.

Linux path:
  - Shared host services stack (one Postgres + one Redis container) at
    ${HOST_SERVICES_COMPOSE}. The script ensures it is up via
    scripts/host-services.sh up before continuing.
  - Per-workspace database 'nexus_<slug>' inside the shared Postgres.
  - APP_PORT chosen by scripts/assign-port.sh <slug> (deterministic, in
    20000..29999). Sail's laravel.test container binds that host port.
  - Vite dev server still runs on the host; the container reaches it via
    VITE_HOST=host.docker.internal (Mac) or the docker host gateway IP.

Both paths are idempotent.
EOF
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    usage
    exit 0
fi

BRANCH="${1:-}"
if [[ -z "$BRANCH" ]]; then
    usage >&2
    exit 64
fi

# Slug: lowercase, replace non-alphanumerics with dashes, strip leading/trailing dashes.
SLUG="$(printf '%s' "$BRANCH" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
if [[ -z "$SLUG" ]]; then
    echo "could not derive slug from '$BRANCH'" >&2
    exit 65
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
WORKTREE_ROOT="$(cd "$REPO_ROOT/.." && pwd)/nexus-ui-worktrees"
WORKTREE_PATH="$WORKTREE_ROOT/$SLUG"
DB_NAME="nexus_${SLUG//-/_}"
# Vite port: deterministic hash of slug in 5173..5299.
VITE_PORT=$((5173 + $(printf '%s' "$SLUG" | cksum | cut -d' ' -f1) % 127))

# ---- Common helpers --------------------------------------------------------

ensure_worktree() {
    mkdir -p "$WORKTREE_ROOT"
    if [[ -d "$WORKTREE_PATH" ]]; then
        echo "--> worktree already exists at $WORKTREE_PATH"
        return
    fi
    pushd "$REPO_ROOT" >/dev/null
    if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
        git worktree add "$WORKTREE_PATH" "$BRANCH"
    else
        git worktree add -b "$BRANCH" "$WORKTREE_PATH" main
    fi
    popd >/dev/null
}

# write_env_var <key> <value> — idempotent set or insert into ./.env
write_env_var() {
    local key="$1" value="$2"
    python3 - "$key" "$value" <<'PYENV'
import re, sys, pathlib
key, value = sys.argv[1], sys.argv[2]
env = pathlib.Path(".env")
text = env.read_text() if env.exists() else ""
pattern = rf"^{re.escape(key)}=.*$"
if re.search(pattern, text, flags=re.MULTILINE):
    text = re.sub(pattern, f"{key}={value}", text, flags=re.MULTILINE)
else:
    if text and not text.endswith("\n"):
        text += "\n"
    text += f"{key}={value}\n"
env.write_text(text)
PYENV
}

install_deps() {
    if [[ ! -d vendor ]]; then
        composer install
    fi
    if [[ ! -d node_modules ]]; then
        if command -v pnpm >/dev/null; then pnpm install; else npm install; fi
    fi
}

generate_app_key_if_missing() {
    if grep -qE '^APP_KEY=base64:' .env; then
        echo "--> APP_KEY already set"
    else
        php artisan key:generate --ansi
    fi
}

start_vite_dev() {
    local pid_file="$WORKTREE_PATH/storage/app/dev-server.pid"
    local log_file="$WORKTREE_PATH/storage/logs/dev-server.log"
    mkdir -p "$(dirname "$pid_file")" "$(dirname "$log_file")"

    if [[ -f "$pid_file" ]] && kill -0 "$(cat "$pid_file")" 2>/dev/null; then
        echo "--> dev server already running (pid $(cat "$pid_file"))"
        return
    fi

    local cmd
    if command -v pnpm >/dev/null; then cmd=(pnpm dev); else cmd=(npm run dev); fi
    echo "--> starting dev server: ${cmd[*]} (logs: $log_file)"
    nohup "${cmd[@]}" >"$log_file" 2>&1 &
    echo $! >"$pid_file"
}

# ---- Mac (Darwin / Herd) helpers ------------------------------------------

mac_ensure_db() {
    if psql -h 127.0.0.1 -U postgres -p 5432 -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx "$DB_NAME"; then
        echo "--> database $DB_NAME already exists, skipping create"
    else
        echo "--> creating database $DB_NAME on Herd Postgres"
        createdb -h 127.0.0.1 -U postgres -p 5432 "$DB_NAME"
    fi
}

mac_write_env() {
    [[ -f .env ]] || cp .env.example .env
    write_env_var APP_URL "https://$SLUG.test"
    write_env_var DB_DATABASE "$DB_NAME"
    write_env_var DB_HOST "127.0.0.1"
    write_env_var REDIS_PREFIX "nexus:$SLUG:"
    write_env_var VITE_PORT "$VITE_PORT"
    write_env_var VITE_HOST "host.docker.internal"
}

# ---- Linux (Sail) helpers --------------------------------------------------

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
        psql -U nexus -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" || true)"
    if [[ "$exists" == "1" ]]; then
        echo "--> database $DB_NAME already exists in shared Postgres"
    else
        echo "--> creating database $DB_NAME in shared Postgres"
        docker compose -f "$HOST_SERVICES_COMPOSE" exec -T pgsql \
            createdb -U nexus "$DB_NAME"
    fi
}

linux_compute_port() {
    "$SCRIPT_DIR/assign-port.sh" "$SLUG"
}

# Docker host gateway IP — what the laravel.test container uses to reach
# the host's Vite dev server. On Mac docker-desktop maps host.docker.internal,
# on Linux we read the bridge gateway from `docker network inspect bridge`.
linux_host_gateway() {
    docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}' 2>/dev/null || echo "172.17.0.1"
}

linux_write_env() {
    local app_port="$1"
    [[ -f .env ]] || cp .env.example .env
    write_env_var APP_URL "http://localhost:$app_port"
    write_env_var APP_PORT "$app_port"
    write_env_var DB_DATABASE "$DB_NAME"
    write_env_var DB_HOST "$(linux_host_gateway)"
    write_env_var DB_USERNAME "nexus"
    write_env_var DB_PASSWORD "nexus"
    write_env_var REDIS_HOST "$(linux_host_gateway)"
    write_env_var REDIS_PREFIX "nexus:$SLUG:"
    write_env_var VITE_PORT "$VITE_PORT"
    write_env_var VITE_HOST "$(linux_host_gateway)"
}

linux_sail_up() {
    if [[ ! -x ./vendor/bin/sail ]]; then
        echo "    sail binary missing — run composer install first" >&2
        return 1
    fi
    ./vendor/bin/sail up -d
}

# ---- Main ------------------------------------------------------------------

mkdir -p "$WORKTREE_ROOT"

echo "==> Worktree for branch '$BRANCH' (slug: $SLUG, platform: $PLATFORM)"
echo "    path:  $WORKTREE_PATH"
echo "    db:    $DB_NAME"
echo "    vite:  $VITE_PORT"

ensure_worktree
cd "$WORKTREE_PATH"
[[ -f .env ]] || cp .env.example .env

case "${PLATFORM}" in
    Darwin)
        mac_ensure_db
        mac_write_env
        echo "    url:   https://$SLUG.test"
        ;;
    Linux)
        linux_ensure_host_services
        linux_ensure_db
        APP_PORT_VALUE="$(linux_compute_port)"
        linux_write_env "$APP_PORT_VALUE"
        echo "    port:  $APP_PORT_VALUE"
        echo "    url:   http://localhost:$APP_PORT_VALUE"
        ;;
    *)
        echo "Unsupported platform: $PLATFORM (need Darwin or Linux)" >&2
        exit 1
        ;;
esac

install_deps
generate_app_key_if_missing
php artisan migrate --force
php artisan db:seed --force

if [[ "$PLATFORM" == "Linux" ]]; then
    linux_sail_up
fi

start_vite_dev

echo ""
echo "✅ Worktree ready."
echo ""
echo "Login: wardy484@gmail.com / password"
echo ""
echo "Next:"
echo "  cd $WORKTREE_PATH"
case "$PLATFORM" in
    Darwin) echo "  open https://$SLUG.test" ;;
    Linux)  echo "  open http://localhost:${APP_PORT_VALUE:-?}" ;;
esac
echo "  php artisan spec:check --next"

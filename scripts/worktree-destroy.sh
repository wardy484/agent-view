#!/usr/bin/env bash
# worktree-destroy.sh — clean up an isolated worktree after a PR merges.
#
# Platform-aware via `uname -s`:
#   Darwin → Herd path: drop DB on Herd's Postgres, flush Redis namespace.
#   Linux  → Sail path: drop DB inside the SHARED host services container
#            (the stack itself survives), `sail down` the worktree's
#            compose project, flush Redis namespace inside the container.
#
# Idempotent: every step checks for existence before mutating.
#
# Usage:
#   scripts/worktree-destroy.sh <branch-name>
#   scripts/worktree-destroy.sh --help

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

Tears down a worktree provisioned by worktree-bootstrap.sh.

Darwin (Mac) path:
  - dropdb against Herd's Postgres on 127.0.0.1:5432.
  - Flush Redis keys matching nexus:<slug>:* via local redis-cli.
  - git worktree remove + delete branch if merged.

Linux path:
  - Drop the per-workspace database 'nexus_<slug>' INSIDE the shared
    Postgres container at ${HOST_SERVICES_COMPOSE}.
    The shared services stack itself is NEVER torn down here — it is
    shared across every worktree on this host.
  - sail down -v for the worktree's compose project (frees the
    laravel.test container and its assigned port).
  - Flush Redis keys matching nexus:<slug>:* inside the container.
  - git worktree remove + delete branch if merged.

Re-running on an already-destroyed worktree is a no-op.
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

SLUG="$(printf '%s' "$BRANCH" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
REPO_ROOT="$(git rev-parse --show-toplevel)"
WORKTREE_ROOT="$(cd "$REPO_ROOT/.." && pwd)/nexus-ui-worktrees"
WORKTREE_PATH="$WORKTREE_ROOT/$SLUG"
DB_NAME="nexus_${SLUG//-/_}"
REDIS_PREFIX="nexus:$SLUG:"

echo "==> Tearing down worktree '$BRANCH' (slug: $SLUG, platform: $PLATFORM)"

# ---- Mac (Darwin / Herd) ---------------------------------------------------

mac_flush_redis() {
    if ! command -v redis-cli >/dev/null; then return; fi
    local keys
    keys="$(redis-cli --scan --pattern "${REDIS_PREFIX}*" || true)"
    if [[ -n "$keys" ]]; then
        echo "--> flushing Redis keys matching ${REDIS_PREFIX}*"
        echo "$keys" | xargs -r redis-cli DEL >/dev/null
    fi
}

mac_drop_db() {
    if psql -h 127.0.0.1 -U postgres -p 5432 -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx "$DB_NAME"; then
        echo "--> dropping database $DB_NAME on Herd Postgres"
        dropdb -h 127.0.0.1 -U postgres -p 5432 "$DB_NAME"
    fi
}

# ---- Linux (Sail) ----------------------------------------------------------

linux_sail_down() {
    if [[ -d "$WORKTREE_PATH" && -x "$WORKTREE_PATH/vendor/bin/sail" ]]; then
        echo "--> sail down for worktree compose project"
        ( cd "$WORKTREE_PATH" && ./vendor/bin/sail down -v ) || true
    fi
}

linux_flush_redis() {
    if [[ ! -f "$HOST_SERVICES_COMPOSE" ]]; then return; fi
    docker compose -f "$HOST_SERVICES_COMPOSE" exec -T redis sh -c \
        "redis-cli --scan --pattern '${REDIS_PREFIX}*' | xargs -r redis-cli DEL" \
        >/dev/null 2>&1 || true
}

linux_drop_db() {
    if [[ ! -f "$HOST_SERVICES_COMPOSE" ]]; then
        echo "--> host services compose not found, skipping DB drop"
        return
    fi
    local exists
    exists="$(docker compose -f "$HOST_SERVICES_COMPOSE" exec -T pgsql \
        psql -U nexus -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" 2>/dev/null || true)"
    if [[ "$exists" == "1" ]]; then
        echo "--> dropping database $DB_NAME inside shared Postgres container"
        docker compose -f "$HOST_SERVICES_COMPOSE" exec -T pgsql \
            dropdb -U nexus "$DB_NAME"
    fi
}

# ---- Common: git worktree + branch -----------------------------------------

remove_worktree() {
    if [[ -d "$WORKTREE_PATH" ]]; then
        pushd "$REPO_ROOT" >/dev/null
        git worktree remove --force "$WORKTREE_PATH" || true
        popd >/dev/null
    fi
}

delete_merged_branch() {
    pushd "$REPO_ROOT" >/dev/null
    if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
        if git branch --merged main | grep -q "^\s*$BRANCH$"; then
            echo "--> deleting merged branch $BRANCH"
            git branch -d "$BRANCH"
        else
            echo "--> branch $BRANCH is not merged into main; leaving it alone"
        fi
    fi
    popd >/dev/null
}

# ---- Main ------------------------------------------------------------------

case "${PLATFORM}" in
    Darwin)
        mac_flush_redis
        mac_drop_db
        ;;
    Linux)
        linux_sail_down
        linux_flush_redis
        linux_drop_db
        ;;
    *)
        echo "Unsupported platform: $PLATFORM (need Darwin or Linux)" >&2
        exit 1
        ;;
esac

remove_worktree
delete_merged_branch

echo "✅ Worktree destroyed."

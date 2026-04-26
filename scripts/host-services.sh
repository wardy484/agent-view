#!/usr/bin/env bash
# Manage the shared Nexus-UI host services stack (one Postgres + one Redis
# container shared across every worktree on this host).
#
# Usage:
#   scripts/host-services.sh up        Start the stack (idempotent).
#   scripts/host-services.sh down      Stop the stack. Affects EVERY worktree.
#   scripts/host-services.sh status    Show running services and health.
#   scripts/host-services.sh psql      Open a psql shell as the nexus user.
#   scripts/host-services.sh redis-cli Open a redis-cli shell.
#   scripts/host-services.sh --help    This message.
#
# Hard rule: agents must NOT call `down` from inside a worktree expecting
# only to clean up that worktree. Per-worktree teardown is handled by
# scripts/worktree-destroy.sh, which drops the worktree's database but
# leaves this stack running.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE_FILE="${REPO_ROOT}/infra/host-services/compose.yaml"

if [[ ! -f "${COMPOSE_FILE}" ]]; then
    echo "host-services compose file not found at ${COMPOSE_FILE}" >&2
    exit 1
fi

usage() {
    sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'
}

cmd_up() {
    docker compose -f "${COMPOSE_FILE}" up -d --wait
    echo ""
    echo "Host services stack is up. Postgres on ${NEXUS_PGSQL_HOST_PORT:-5432}, Redis on ${NEXUS_REDIS_HOST_PORT:-6379}."
}

cmd_down() {
    docker compose -f "${COMPOSE_FILE}" down
}

cmd_status() {
    docker compose -f "${COMPOSE_FILE}" ps
}

cmd_psql() {
    docker compose -f "${COMPOSE_FILE}" exec pgsql psql -U nexus -d nexus_template
}

cmd_redis_cli() {
    docker compose -f "${COMPOSE_FILE}" exec redis redis-cli
}

case "${1:-}" in
    up)        cmd_up ;;
    down)      cmd_down ;;
    status)    cmd_status ;;
    psql)      cmd_psql ;;
    redis-cli) cmd_redis_cli ;;
    -h|--help|"") usage ;;
    *)
        echo "Unknown subcommand: ${1}" >&2
        echo "" >&2
        usage >&2
        exit 1
        ;;
esac

#!/usr/bin/env bash
# Compute a deterministic host port for a given workspace slug, stable
# across re-runs and across hosts. Used by worktree-bootstrap.sh on Linux
# (Sail) to pick the laravel.test container's host binding so parallel
# Polyscope worktrees never collide on port 80.
#
# Usage:
#   scripts/assign-port.sh <workspace-slug>
#
# Algorithm: APP_PORT = 20000 + (crc32(slug) % 10000)
# The 10k window from 20000–29999 leaves room for ~10k distinct workspaces
# before pigeon-holing forces a collision; in practice you have <50.

set -euo pipefail

if [[ $# -lt 1 || -z "${1:-}" ]]; then
    echo "Usage: $0 <workspace-slug>" >&2
    exit 1
fi

slug="$1"

# crc32 via PHP — already a hard project dependency, no extra tooling.
hash="$(php -r "echo crc32(\$argv[1]);" -- "${slug}")"

port=$((20000 + (hash % 10000)))

echo "${port}"

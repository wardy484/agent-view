#!/usr/bin/env bash
# worktree-destroy.sh — clean up an isolated worktree after a PR merges.
#
# Usage: ./scripts/worktree-destroy.sh <branch-name>

set -euo pipefail

BRANCH="${1:-}"
if [[ -z "$BRANCH" ]]; then
  echo "usage: $0 <branch-name>" >&2
  exit 64
fi

SLUG="$(printf '%s' "$BRANCH" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
REPO_ROOT="$(git rev-parse --show-toplevel)"
WORKTREE_ROOT="$(cd "$REPO_ROOT/.." && pwd)/nexus-ui-worktrees"
WORKTREE_PATH="$WORKTREE_ROOT/$SLUG"
DB_NAME="nexus_${SLUG//-/_}"
REDIS_PREFIX="nexus:$SLUG:"

echo "==> Tearing down worktree '$BRANCH' (slug: $SLUG)"

# 1. Flush Redis namespace.
if command -v redis-cli >/dev/null; then
  KEYS=$(redis-cli --scan --pattern "${REDIS_PREFIX}*" || true)
  if [[ -n "$KEYS" ]]; then
    echo "--> flushing Redis keys matching $REDIS_PREFIX*"
    echo "$KEYS" | xargs -r redis-cli DEL >/dev/null
  fi
fi

# 2. Drop Postgres DB.
if psql -h 127.0.0.1 -U postgres -p 5432 -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx "$DB_NAME"; then
  echo "--> dropping database $DB_NAME"
  dropdb -h 127.0.0.1 -U postgres -p 5432 "$DB_NAME"
fi

# 3. Remove git worktree.
if [[ -d "$WORKTREE_PATH" ]]; then
  pushd "$REPO_ROOT" >/dev/null
  git worktree remove --force "$WORKTREE_PATH" || true
  popd >/dev/null
fi

# 4. Delete the branch if merged.
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

echo "✅ Worktree destroyed."

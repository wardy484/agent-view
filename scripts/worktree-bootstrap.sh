#!/usr/bin/env bash
# worktree-bootstrap.sh — idempotently provision an isolated worktree for parallel work.
#
# Usage: ./scripts/worktree-bootstrap.sh <branch-name>
#
# Creates:
#   • git worktree at ../nexus-ui-worktrees/<slug> (branches off `main` if new)
#   • Postgres DB `nexus_<slug>` via local Herd
#   • Per-worktree .env with APP_URL, DB_DATABASE, REDIS_PREFIX, VITE_PORT
#   • Installed Composer + pnpm deps
#   • Migrated + seeded DB
#
# Safe to re-run — all steps check for existing state.

set -euo pipefail

BRANCH="${1:-}"
if [[ -z "$BRANCH" ]]; then
  echo "usage: $0 <branch-name>" >&2
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

mkdir -p "$WORKTREE_ROOT"

echo "==> Worktree for branch '$BRANCH' (slug: $SLUG)"
echo "    path:     $WORKTREE_PATH"
echo "    db:       $DB_NAME"
echo "    url:      https://$SLUG.test"
echo "    vite:     $VITE_PORT"

# 1. git worktree
if [[ -d "$WORKTREE_PATH" ]]; then
  echo "--> worktree already exists, skipping git worktree add"
else
  pushd "$REPO_ROOT" >/dev/null
  if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    git worktree add "$WORKTREE_PATH" "$BRANCH"
  else
    git worktree add -b "$BRANCH" "$WORKTREE_PATH" main
  fi
  popd >/dev/null
fi

# 2. Postgres DB (Herd's bundled Postgres, trust auth, user=postgres, no password).
if psql -h 127.0.0.1 -U postgres -p 5432 -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx "$DB_NAME"; then
  echo "--> database $DB_NAME already exists, skipping create"
else
  echo "--> creating database $DB_NAME"
  createdb -h 127.0.0.1 -U postgres -p 5432 "$DB_NAME"
fi

# 3. .env
cd "$WORKTREE_PATH"
if [[ ! -f .env ]]; then
  cp .env.example .env
fi

python3 - <<PYENV
import re, pathlib
env_path = pathlib.Path(".env")
text = env_path.read_text()
replacements = {
    "APP_URL": "https://$SLUG.test",
    "DB_DATABASE": "$DB_NAME",
    "REDIS_PREFIX": "nexus:$SLUG:",
    "VITE_PORT": "$VITE_PORT",
}
for key, value in replacements.items():
    pattern = rf"^{re.escape(key)}=.*$"
    if re.search(pattern, text, flags=re.MULTILINE):
        text = re.sub(pattern, f"{key}={value}", text, flags=re.MULTILINE)
    else:
        text += f"\n{key}={value}\n"
env_path.write_text(text)
PYENV

# 4. Deps
if [[ ! -d vendor ]]; then
  composer install
fi
if [[ ! -d node_modules ]]; then
  if command -v pnpm >/dev/null; then
    pnpm install
  else
    npm install
  fi
fi

# 5. APP_KEY + migrate
if grep -qE '^APP_KEY=base64:' .env; then
  echo "--> APP_KEY already set"
else
  php artisan key:generate --ansi
fi
php artisan migrate --force

echo ""
echo "✅ Worktree ready."
echo ""
echo "Next:"
echo "  cd $WORKTREE_PATH"
echo "  pnpm dev                    # Vite on port $VITE_PORT"
echo "  open https://$SLUG.test     # Herd-served URL"
echo "  php artisan spec:check --next"

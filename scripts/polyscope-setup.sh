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

FOLDER="${1:-${PWD##*/}}"
SLUG="$(printf '%s' "$FOLDER" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
DB_NAME="nexus_${SLUG//-/_}"
VITE_PORT=$((5173 + $(printf '%s' "$SLUG" | cksum | cut -d' ' -f1) % 127))

echo "==> Polyscope setup for '$FOLDER' (slug: $SLUG)"
echo "    db:   $DB_NAME"
echo "    url:  https://$SLUG.test"
echo "    vite: $VITE_PORT"

# 1. Postgres DB (Herd's bundled Postgres, trust auth, user=postgres).
if psql -h 127.0.0.1 -U postgres -p 5432 -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx "$DB_NAME"; then
  echo "--> database $DB_NAME already exists"
else
  echo "--> creating database $DB_NAME"
  createdb -h 127.0.0.1 -U postgres -p 5432 "$DB_NAME"
fi

# 2. .env (copy from example, then patch the per-workspace values).
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

# 3. Deps.
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

# 4. APP_KEY.
if grep -qE '^APP_KEY=base64:' .env; then
  echo "--> APP_KEY already set"
else
  php artisan key:generate --ansi
fi

# 5. Migrate + seed (seeder is idempotent — wardy484@gmail.com / password
#    plus one demo snapshot per view_type).
php artisan migrate --force
php artisan db:seed --force

echo ""
echo "✅ Polyscope workspace ready."
echo "   Login: wardy484@gmail.com / password"
echo "   URL:   https://$SLUG.test"

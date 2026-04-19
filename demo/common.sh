#!/usr/bin/env bash
# demo/common.sh — shared helpers for milestone demo scripts.
#
# Source this at the top of each demo/m*.sh:
#   source "$(dirname "$0")/common.sh"

set -euo pipefail

# Colours (only when TTY).
if [[ -t 1 ]]; then
  C_BOLD=$(tput bold); C_DIM=$(tput dim); C_GREEN=$(tput setaf 2); C_RED=$(tput setaf 1); C_RESET=$(tput sgr0)
else
  C_BOLD=''; C_DIM=''; C_GREEN=''; C_RED=''; C_RESET=''
fi

say() { printf '%s==>%s %s\n' "$C_BOLD" "$C_RESET" "$*"; }
ok()  { printf '%s✓%s %s\n' "$C_GREEN" "$C_RESET" "$*"; }
die() { printf '%s✗%s %s\n' "$C_RED" "$C_RESET" "$*" >&2; exit 1; }

# Reset the DB to a known state.
demo_reset_db() {
  say "reset database"
  php artisan migrate:fresh --seed --force
}

# Mint a Sanctum token scoped to a workbench_slug.
#
# Usage: TOKEN=$(demo_mint_token "nexus-demo")
demo_mint_token() {
  local workbench_slug="${1:?workbench_slug required}"
  php artisan tinker --execute "
    \$user = App\\Models\\User::query()->firstOrCreate(
      ['email' => 'demo@nexus.test'],
      ['name' => 'Demo', 'password' => bcrypt('password')],
    );
    echo \$user->createToken('demo-{$workbench_slug}', ['workbench:{$workbench_slug}'])->plainTextToken;
  " 2>/dev/null | tail -n 1
}

# POST a payload to the MCP HTTP endpoint.
#
# Usage: demo_mcp_call "$TOKEN" "present_structured_data" '{"workbench_slug":"..."}'
demo_mcp_call() {
  local token="${1:?token required}"
  local tool="${2:?tool name required}"
  local args_json="${3:?args JSON required}"
  local url="${APP_URL:-http://127.0.0.1:8000}/ai/tools/${tool}"

  php -r "
    \$ch = curl_init('$url');
    curl_setopt_array(\$ch, [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Authorization: Bearer $token', 'Content-Type: application/json'],
      CURLOPT_POSTFIELDS => '$args_json',
    ]);
    echo curl_exec(\$ch);
  "
}

#!/usr/bin/env bash
# mint-preview-token.sh <env-id> <workbench-slug> [pr-number]
# Creates a preview-only Sanctum personal access token via tinker.
# Writes the plain-text token to storage/skill-ship-req/<pr>.json and
# prints that path on stdout. NEVER echoes the token to stdout.
set -euo pipefail

ENV_ID="${1:?usage: mint-preview-token.sh <env-id> <workbench-slug> [pr-number]}"
SLUG="${2:?}"
PR_NUMBER="${3:-manual}"

REPO_ROOT="$(git rev-parse --show-toplevel)"
OUT_DIR="${REPO_ROOT}/storage/skill-ship-req"
mkdir -p "${OUT_DIR}"
OUT_FILE="${OUT_DIR}/${PR_NUMBER}.json"

if ! echo "${SLUG}" | grep -qE '^[a-z0-9]+(-[a-z0-9]+)*$'; then
    echo "workbench slug must be lowercase-kebab" >&2
    exit 2
fi

TINKER_PHP=$(cat <<PHP
\$user = \App\Models\User::firstOrCreate(
    ['email' => 'agent@preview.nexus-ui'],
    ['name' => 'Preview Agent', 'password' => bcrypt(str()->random(40))],
);
echo \$user->createToken(
    'claude-preview-' . now()->timestamp,
    ['workbench:${SLUG}']
)->plainTextToken;
PHP
)

RESPONSE="$(cloud command:run "${ENV_ID}" \
    --cmd="php artisan tinker --execute='${TINKER_PHP}'" \
    --json --no-monitor 2>/dev/null)"

# Token is plain-text output of the tinker command. The CLI wraps stdout
# in a JSON envelope; extract and trim.
TOKEN="$(echo "${RESPONSE}" | jq -r '.output // .stdout // empty' \
    | tr -d '\r\n' | sed -E 's/.*([0-9]+\|[A-Za-z0-9]{40,}).*/\1/')"

if [[ -z "${TOKEN}" || "${TOKEN}" == *"|"*"|"* || "${TOKEN}" != *"|"* ]]; then
    echo "failed to parse token from preview response" >&2
    echo "${RESPONSE}" >&2
    exit 3
fi

umask 077
jq -n --arg env "${ENV_ID}" --arg slug "${SLUG}" --arg token "${TOKEN}" \
    --arg pr "${PR_NUMBER}" '{env_id:$env, workbench_slug:$slug, token:$token, pr_number:$pr, minted_at: now | todate}' \
    >"${OUT_FILE}"

echo "${OUT_FILE}"

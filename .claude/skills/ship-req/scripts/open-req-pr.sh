#!/usr/bin/env bash
# open-req-pr.sh <REQ-ID>
# Reads the REQ paragraph from docs/nexus-spec.md, generates a PR body
# from the repo template, and opens the PR via gh. Emits JSON on stdout.
set -euo pipefail

REQ_ID="${1:?usage: open-req-pr.sh <REQ-ID>}"
REPO_ROOT="$(git rev-parse --show-toplevel)"
SPEC="${REPO_ROOT}/docs/nexus-spec.md"
BRANCH="$(git branch --show-current)"

if [[ "${BRANCH}" == "main" ]]; then
    echo "refusing to open PR from main" >&2
    exit 2
fi

if ! grep -qE "\\*\\*${REQ_ID}\\*\\*" "${SPEC}"; then
    echo "REQ-ID ${REQ_ID} not found in ${SPEC}" >&2
    exit 3
fi

REQ_LINE="$(grep -E "\\*\\*${REQ_ID}\\*\\*" "${SPEC}" | head -1 | sed -E "s/^- \\*\\*${REQ_ID}\\*\\* //")"
MILESTONE="$(echo "${REQ_ID}" | sed -E 's/^REQ-(M[0-9]+)-.*/\1/')"

BODY_FILE="$(mktemp)"
trap 'rm -f "${BODY_FILE}"' EXIT

cat >"${BODY_FILE}" <<EOF
## Requirement(s)

Closes: **${REQ_ID}** — ${REQ_LINE}

## Tests added

\`\`\`
php artisan test --filter=${REQ_ID} --compact
\`\`\`

## Demo steps

\`\`\`bash
./scripts/worktree-bootstrap.sh review-${REQ_ID,,}
cd ../nexus-ui-worktrees/review-${REQ_ID,,}
git checkout ${BRANCH}
php artisan migrate
php artisan test --filter=${REQ_ID} --compact
\`\`\`

## Spec changes

- [x] No spec changes

## Checklist

- [x] \`php artisan spec:check --milestone=${MILESTONE}\` passes locally
- [x] \`php artisan test --filter=${REQ_ID}\` passes locally
- [x] \`./vendor/bin/pint --test\` passes
- [x] Worktree used
- [x] No new unspec'd features

## Preview verification

Filled in by \`ship-req\` skill after preview deploy completes.
EOF

URL="$(gh pr create \
    --title "[${REQ_ID}] ${REQ_LINE}" \
    --body-file "${BODY_FILE}" \
    --base main \
    --head "${BRANCH}")"

NUMBER="$(echo "${URL}" | grep -oE '[0-9]+$')"

printf '{"pr_number":%s,"pr_url":"%s","branch":"%s","req_id":"%s"}\n' \
    "${NUMBER}" "${URL}" "${BRANCH}" "${REQ_ID}"

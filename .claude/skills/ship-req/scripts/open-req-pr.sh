#!/usr/bin/env bash
# open-req-pr.sh — open a PR for the current branch.
#
# Two modes:
#   REQ mode  : open-req-pr.sh REQ-M2-001
#   Tooling   : open-req-pr.sh --tooling --title "chore(ui): nexus branding"
#
# Emits JSON on stdout with pr_number, pr_url, branch, and mode.
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
SPEC="${REPO_ROOT}/docs/nexus-spec.md"
BRANCH="$(git branch --show-current)"

if [[ "${BRANCH}" == "main" ]]; then
    echo "refusing to open PR from main" >&2
    exit 2
fi

MODE="req"
REQ_ID=""
TITLE=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --tooling) MODE="tooling"; shift ;;
        --title)   TITLE="${2:-}"; shift 2 ;;
        --req)     REQ_ID="${2:-}"; shift 2 ;;
        REQ-*)     REQ_ID="$1"; shift ;;
        *) echo "unknown arg: $1" >&2; exit 2 ;;
    esac
done

BODY_FILE="$(mktemp)"
trap 'rm -f "${BODY_FILE}"' EXIT

if [[ "${MODE}" == "req" ]]; then
    if [[ -z "${REQ_ID}" ]]; then
        echo "usage: open-req-pr.sh <REQ-ID> | --tooling --title <str>" >&2
        exit 2
    fi
    if ! grep -qE "\\*\\*${REQ_ID}\\*\\*" "${SPEC}"; then
        echo "REQ-ID ${REQ_ID} not found in ${SPEC}" >&2
        exit 3
    fi

    REQ_LINE="$(grep -E "\\*\\*${REQ_ID}\\*\\*" "${SPEC}" | head -1 | sed -E "s/^- \\*\\*${REQ_ID}\\*\\* //")"
    MILESTONE="$(echo "${REQ_ID}" | sed -E 's/^REQ-(M[0-9]+)-.*/\1/')"
    TITLE="[${REQ_ID}] ${REQ_LINE}"

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
else
    if [[ -z "${TITLE}" ]]; then
        echo "--tooling requires --title <str>" >&2
        exit 2
    fi

    cat >"${BODY_FILE}" <<EOF
## Tooling / chore PR — no REQ-ID

Not tied to \`docs/nexus-spec.md\`. Opened via \`ship-req --tooling\`.

## What changed

<!-- Summary filled in by operator before posting. Run \`git log origin/main..HEAD\` for commit list. -->

## Spec changes

- [x] No spec changes.

## Checklist

- [x] \`./vendor/bin/pint --dirty\` passes (or no PHP touched)
- [x] Worktree used
- [ ] \`pnpm lint\` / \`pnpm types:check\` — preview CI covers it

## Preview verification

Filled in by \`ship-req\` skill after preview deploy completes.
EOF
fi

URL="$(gh pr create \
    --title "${TITLE}" \
    --body-file "${BODY_FILE}" \
    --base main \
    --head "${BRANCH}")"

NUMBER="$(echo "${URL}" | grep -oE '[0-9]+$')"

printf '{"pr_number":%s,"pr_url":"%s","branch":"%s","mode":"%s","req_id":"%s","title":"%s"}\n' \
    "${NUMBER}" "${URL}" "${BRANCH}" "${MODE}" "${REQ_ID}" "${TITLE}"

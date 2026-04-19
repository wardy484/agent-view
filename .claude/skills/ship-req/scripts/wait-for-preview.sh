#!/usr/bin/env bash
# wait-for-preview.sh <pr-number>
# Polls `cloud environment:list` until the branch's preview env is running
# and its latest deployment is finished. Emits JSON on stdout.
set -euo pipefail

PR_NUMBER="${1:?usage: wait-for-preview.sh <pr-number>}"
BRANCH="$(gh pr view "${PR_NUMBER}" --json headRefName -q .headRefName)"
DEADLINE=$(( $(date +%s) + 1200 ))     # 20 min

match_env() {
    # filter envs to the branch OR name containing pr-<n>; never production
    cloud environment:list --json 2>/dev/null \
        | jq -r --arg branch "${BRANCH}" --arg pr "pr-${PR_NUMBER}" '
            .[]
            | select(.name != "production" and .slug != "production")
            | select((.branch // "") == $branch or (.name // "" | contains($pr)))
            | {id, url, name, branch, status, currentDeploymentId}
        ' \
        | jq -s '.[0] // empty'
}

latest_deployment_status() {
    local env_id="$1"
    cloud deployment:list "${env_id}" --json 2>/dev/null \
        | jq -r '.[0].status // "unknown"'
}

while (( $(date +%s) < DEADLINE )); do
    ENV_JSON="$(match_env || true)"

    if [[ -z "${ENV_JSON}" || "${ENV_JSON}" == "null" ]]; then
        echo "[ship-req] waiting for preview env for branch ${BRANCH}…" >&2
        sleep 15
        continue
    fi

    ENV_ID="$(echo "${ENV_JSON}" | jq -r .id)"
    ENV_URL="$(echo "${ENV_JSON}" | jq -r .url)"
    ENV_STATUS="$(echo "${ENV_JSON}" | jq -r .status)"
    DEPLOY_STATUS="$(latest_deployment_status "${ENV_ID}")"

    echo "[ship-req] env=${ENV_ID} status=${ENV_STATUS} deploy=${DEPLOY_STATUS}" >&2

    if [[ "${ENV_STATUS}" == "running" && "${DEPLOY_STATUS}" == "finished" ]]; then
        printf '{"env_id":"%s","url":"%s","branch":"%s","deploy_status":"%s"}\n' \
            "${ENV_ID}" "${ENV_URL}" "${BRANCH}" "${DEPLOY_STATUS}"
        exit 0
    fi

    if [[ "${DEPLOY_STATUS}" == "failed" || "${DEPLOY_STATUS}" == "errored" ]]; then
        echo "preview deploy failed for env ${ENV_ID}" >&2
        exit 4
    fi

    sleep 15
done

echo "timed out waiting for preview env (branch ${BRANCH})" >&2
exit 5

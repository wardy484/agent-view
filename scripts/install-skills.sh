#!/usr/bin/env bash
# Install repo-local skills (under .skills/) into the AI tools the user has
# set up locally: Claude Code, Cursor, Codex. Idempotent; safe to re-run.
#
# Source of truth: .skills/<name>/SKILL.md (checked into the repo).
# We symlink rather than copy so a `git pull` updates every tool at once.
#
# Skip targets whose tool isn't installed (no ~/.claude, no ~/.cursor, etc.).

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SKILLS_DIR="${REPO_ROOT}/.skills"

if [[ ! -d "${SKILLS_DIR}" ]]; then
    echo "No .skills/ directory at ${SKILLS_DIR} — nothing to install." >&2
    exit 1
fi

shopt -s nullglob
SKILL_DIRS=("${SKILLS_DIR}"/*/)
shopt -u nullglob

if [[ ${#SKILL_DIRS[@]} -eq 0 ]]; then
    echo "No skills found under ${SKILLS_DIR}." >&2
    exit 0
fi

# --- Claude Code ---------------------------------------------------------
CLAUDE_SKILLS="${HOME}/.claude/skills"
if [[ -d "${HOME}/.claude" ]]; then
    mkdir -p "${CLAUDE_SKILLS}"
    for skill_dir in "${SKILL_DIRS[@]}"; do
        name="$(basename "${skill_dir}")"
        target="${CLAUDE_SKILLS}/${name}"
        rm -rf "${target}"
        ln -s "${skill_dir%/}" "${target}"
        echo "claude  → ${target} → ${skill_dir%/}"
    done
else
    echo "claude  → skipped (no ~/.claude)"
fi

# --- Cursor --------------------------------------------------------------
CURSOR_RULES="${HOME}/.cursor/rules"
if [[ -d "${HOME}/.cursor" ]]; then
    mkdir -p "${CURSOR_RULES}"
    for skill_dir in "${SKILL_DIRS[@]}"; do
        name="$(basename "${skill_dir}")"
        src="${skill_dir}SKILL.md"
        target="${CURSOR_RULES}/${name}.md"
        if [[ ! -f "${src}" ]]; then
            echo "cursor  → skipping ${name} (no SKILL.md)"
            continue
        fi
        rm -f "${target}"
        ln -s "${src}" "${target}"
        echo "cursor  → ${target} → ${src}"
    done
else
    echo "cursor  → skipped (no ~/.cursor)"
fi

# --- Codex ---------------------------------------------------------------
# Codex has no skill system; it reads project AGENTS.md. We append a
# pointer block once (idempotent — guarded by a marker comment).
CODEX_DIR="${HOME}/.codex"
AGENTS_MD="${REPO_ROOT}/AGENTS.md"
MARKER="<!-- managed-by: scripts/install-skills.sh -->"

if [[ -d "${CODEX_DIR}" || -n "${CODEX_HOME:-}" ]]; then
    if [[ -f "${AGENTS_MD}" ]] && ! grep -qF "${MARKER}" "${AGENTS_MD}"; then
        {
            echo ""
            echo "${MARKER}"
            echo "## Repo-local skills"
            echo ""
            echo "User-invocable skill definitions for this project live at"
            echo "\`.skills/<name>/SKILL.md\`. When the user invokes one by name"
            echo "(e.g. \`/feature\`, \`/ui-review\`), follow that file's instructions."
            echo ""
            for skill_dir in "${SKILL_DIRS[@]}"; do
                name="$(basename "${skill_dir}")"
                echo "- \`/${name}\` — see \`.skills/${name}/SKILL.md\`"
            done
        } >> "${AGENTS_MD}"
        echo "codex   → appended skill pointers to ${AGENTS_MD}"
    else
        echo "codex   → AGENTS.md already references skills (or missing)"
    fi
else
    echo "codex   → skipped (no ~/.codex)"
fi

echo ""
echo "Done. Reload your AI tool's session to pick up new skills."

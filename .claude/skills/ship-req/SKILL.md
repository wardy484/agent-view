---
name: ship-req
description: "Use this skill to ship a single Nexus-UI REQ-ID end-to-end: open a PR from the current worktree branch, wait for the Laravel Cloud preview environment to finish deploying, mint a Sanctum MCP token against the preview DB, call the MCP tools over HTTPS to prove the REQ behaves, run the spec:check gate on the preview, and report all preview URLs. Trigger when the user says 'ship it', 'open a PR for REQ-…', 'test on preview', 'preview deploy', or any request to prove a change works end-to-end on a real deployed environment rather than only locally. Also activate when the user wants the full close-the-loop workflow for a requirement (code → PR → preview → MCP smoke test → gate → report). Do not activate for local-only work, doc-only edits, or operations that don't need a deployed preview."
license: MIT
metadata:
  author: nexus-ui
---

# ship-req — one REQ-ID, one PR, one preview, proven green

This skill closes the loop between writing M-series code and proving it on a
real Laravel Cloud preview environment. It exists because the only way to
know `present_structured_data` actually renders a `slide_deck` (or any MCP
behaviour) is to hit the deployed app, not `main`.

## Preconditions

Stop and fix before proceeding if any of these fail:

1. **Not on `main`.** `git branch --show-current` must be a feature branch.
   If on `main`, run `./scripts/worktree-bootstrap.sh <branch>` first.
2. **Clean or intentionally staged working tree.** `git status --short` is
   reviewed — commit or stash anything unrelated.
3. **REQ-ID is in `docs/nexus-spec.md`.** If missing, open a spec PR first
   (per AGENTS.md).
4. **Pest test(s) exist for the REQ-ID.** `php artisan spec:check --next
   --milestone=<M>` should NOT print the REQ-ID you're shipping.
5. **`gh` auth, `cloud` CLI auth.** `gh auth status` and `cloud auth` both green.

## The 7-step ship loop

For a REQ-ID `REQ-M<n>-<nnn>`, run exactly these steps. Most are delegated
to the scripts in `scripts/` so output stays structured and poll-safe.

### 1. Sanity gate (local)

```bash
php artisan spec:check --milestone=M<n>     # target scope only
php artisan test --filter=REQ-M<n>-<nnn> --compact
vendor/bin/pint --dirty --format agent
```

All three must be green. Do not continue otherwise.

### 2. Push branch

```bash
git push -u origin "$(git branch --show-current)"
```

### 3. Open the PR

Two modes — pick one:

```bash
# REQ-shaped work (closes a REQ-ID in docs/nexus-spec.md)
scripts/open-req-pr.sh <REQ-ID>

# Tooling / chore work (branding, CI, scripts, .claude/**, etc.)
scripts/open-req-pr.sh --tooling --title "chore(ui): nexus branding"
```

The REQ mode reads the matching paragraph from `docs/nexus-spec.md` and
generates a template-filled body with closes-tag, demo steps, and checklist.

The tooling mode skips REQ validation and uses a minimal body template —
use this for anything that isn't product-shaped (skill updates, branding,
infra, docs). The preview-gate step still runs, but the preview-side
filtered pest is skipped (nothing to filter on).

Both modes emit `{"pr_number": …, "pr_url": "…", "mode": "req|tooling"}` on stdout.

**Never edit the PR body to claim gates pass that didn't.** If the local
gate in step 1 had known skipped items (e.g. "M2 backlog"), surface that
honestly in a "Known pre-existing failures" section.

### 4. Wait for the preview environment

```bash
scripts/wait-for-preview.sh <pr-number>
```

The script:

- Polls `cloud environment:list --json` for the `nexus-ui` application.
- Matches a preview env whose `branch` equals the branch OR whose name
  contains the PR number (Laravel Cloud naming varies — both are tried).
- Waits until `status` is `running` **and** the latest deployment for that
  env is `status=finished`.
- Emits progress every ~15s; gives up after 20 min with a clear error.
- Prints `{"env_id":"env-…","url":"https://…laravel.cloud","deployment_id":"dep-…"}` on success.

### 5. Mint a preview-scoped Sanctum token

```bash
scripts/mint-preview-token.sh <env-id> <workbench-slug>
```

Runs on the preview via `cloud command:run --cmd='php artisan tinker --execute='…'' --json`:

```php
$user = \App\Models\User::firstOrCreate(
    ['email' => 'agent@preview.nexus-ui'],
    ['name' => 'Preview Agent', 'password' => bcrypt(str()->random(40))],
);
echo $user->createToken(
    'claude-preview-' . now()->timestamp,
    ['workbench:<workbench-slug>']
)->plainTextToken;
```

The token is parsed out of the command's JSON output and written **only**
to `storage/skill-ship-req/<pr-number>.json` (gitignored) — never echoed
in the PR, never committed.

### 6. Prove it via MCP over HTTPS

We do not register a new MCP server in `~/.claude.json` — tool registration
is session-scoped and adding one requires a restart. Instead the skill
calls the MCP endpoint directly via JSON-RPC:

```bash
node scripts/mcp-call.js \
    --url "https://<preview>/ai/mcp/nexus" \
    --token "$TOKEN" \
    --tool "present_structured_data" \
    --args-file fixtures/<req-id>.json
```

`mcp-call.js` is a 40-line JSON-RPC 2.0 client over HTTPS that:

- Issues `initialize` then `tools/call` with the payload.
- Emits the response body + HTTP status as JSON to stdout.
- Exits non-zero on HTTP ≥ 400 or JSON-RPC error.

For each REQ-ID you ship, drop a small fixture in
`.claude/skills/ship-req/fixtures/<req-id>.json` shaped like the tool's
`arguments`. The skill's own PR carries a sample fixture.

### 7. Run the gate on the preview

```bash
cloud command:run <env-id> --cmd='php artisan spec:check --milestone=M<n>' --json
cloud command:run <env-id> --cmd='php artisan test --filter=REQ-M<n>-<nnn> --compact' --json
```

Parse the JSON response — status + output + exit_code. If non-zero, stop
and report; do not claim green.

### 8. Report

Post a single comment on the PR (via `gh pr comment <n> --body-file …`)
containing:

- Preview URL + env ID.
- MCP tool smoke-test response (trimmed to the essentials — tool name,
  HTTP status, snapshot URL returned, any `ui://` resource).
- Preview gate results (spec:check + filtered pest).
- Any known pre-existing failures carried over, explicitly labelled.

Also print the same summary to the operator so they can paste it.

## Fixture & script files

```
.claude/skills/ship-req/
├── SKILL.md                     # this file
├── scripts/
│   ├── open-req-pr.sh           # step 3
│   ├── wait-for-preview.sh      # step 4
│   ├── mint-preview-token.sh    # step 5
│   └── mcp-call.js              # step 6 — JSON-RPC HTTPS client
└── fixtures/
    └── REQ-M3-001.json          # example slide_deck payload
```

## Never do

- Never commit the minted token. `storage/skill-ship-req/` is gitignored
  via the top-level rule; double-check before `git add -A`.
- Never skip step 7 (preview-side gate). Local green ≠ preview green —
  env vars, migrations, and deploy hooks differ.
- Never point the skill at `main`/`production` by accident. The env lookup
  in step 4 refuses to match `production`.
- Never modify `~/.claude.json` from this skill. Session restart pain is
  not worth it; use the HTTPS JSON-RPC path.

## Meta: shipping the skill itself

The first PR that adds this skill is a tooling PR and is not tied to a
REQ-ID. Skip step 5–7 for that PR (there is nothing MCP-shaped to smoke
test). Open a plain PR titled `tooling: .claude skill ship-req` and note
in the body that this bootstraps the workflow future PRs will use.

## Command cheat sheet

```bash
# Inspect preview envs
cloud environment:list --json | jq '.[] | {id, name, branch, status, url}'

# Tail preview logs
cloud environment:logs <env-id>

# Run arbitrary preview command
cloud command:run <env-id> --cmd='php artisan route:list --path=ai' --json
```

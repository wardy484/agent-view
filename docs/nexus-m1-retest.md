# Nexus M1 — Canonical E2E Re-test Plan

Run this whenever a change ships that touches:
`app/Nexus/**`, `app/Mcp/**`, the `present_structured_data` tool,
table schema validation, or `TablePreviewRenderer`.

## How to run

Paste the block below into a Claude Code session that has the
`nexus-prod` MCP server connected. If `nexus-prod` isn't in the MCP
list, run `claude mcp list` first.

Use the MCP tool directly: `mcp__nexus-prod__present_structured_data`.
Do NOT curl the HTTP endpoint to create snapshots. Fetching URLs to
validate rendered HTML is fine (use `ctx_execute` with `fetch`).

Post every returned workbench URL back inline so a human can eyeball
the UI as the run proceeds.

---

## Fixtures

Keep these shapes identical across runs so snapshot diffs are
meaningful.

**Columns (all tests except Test 4):**

```json
[
  {"key": "id",   "label": "ID"},
  {"key": "name", "label": "Name"},
  {"key": "plan", "label": "Plan"},
  {"key": "mrr",  "label": "MRR"}
]
```

**Test 1 rows (5 customers, mixed plans):**

```json
[
  {"id": 1, "name": "Acme Corp",         "plan": "enterprise", "mrr": 4999},
  {"id": 2, "name": "Globex",            "plan": "pro",        "mrr": 499},
  {"id": 3, "name": "Initech",           "plan": "free",       "mrr": 0},
  {"id": 4, "name": "Umbrella",          "plan": "pro",        "mrr": 599},
  {"id": 5, "name": "Stark Industries",  "plan": "enterprise", "mrr": 8999}
]
```

**Test 2 rows:** Test 1 rows plus
`{"id": 6, "name": "Wayne Enterprises", "plan": "enterprise", "mrr": 12999}`.

**Test 3 rows:** 100 rows generated as
`{id: i, name: "Customer "+i, plan: ["pro","enterprise","free"][i%3], mrr: (i%3===0) ? 0 : (i*37)%9999}`
for `i` in 1..100.

**Test 5 rows:**

```json
[
  {"id": 1, "name": "OtherCo",      "plan": "pro",  "mrr": 199},
  {"id": 2, "name": "Isolated Inc", "plan": "free", "mrr": 0}
]
```

---

## Tests

### Test 1 — Happy path (new snapshot)

- `workbench_slug`: `qa-suite`
- `view_type`: `table`
- `title`: `Customer list v1`
- `data_payload`: fixtures above (5 rows)

**Record**: `snapshot_id`, `snapshot_slug`, `revision`, `url`.
**Expect**: revision=1, HTTP 200 on URL, HTML contains "Acme Corp" and
"Stark Industries".

### Test 2 — Append revision (version switcher)

- Same args as Test 1, add `snapshot_id` from Test 1, rows = 6.
- `title`: `Customer list v2`.

**Expect**: same `snapshot_id`, same `snapshot_slug`, `revision=2`.

### Test 3 — Large payload (50-row truncation)

- `workbench_slug`: `qa-large`
- `view_type`: `table`
- `title`: `Large customer list (100 rows)`
- 100 rows per fixture recipe.

**Expect**:
- HTTP 200
- SSR HTML contains `showing 50 of 100`
- SSR HTML contains `Customer 50`
- SSR HTML does **NOT** contain `Customer 51` (truncation boundary)
- SSR HTML length ≤ 64 KB

### Test 4 — Schema rejection (missing `columns`)

- `workbench_slug`: `qa-suite`
- `view_type`: `table`
- `title`: `Missing columns test`
- `data_payload`: `{"rows": [{"id": 1, "name": "NoColumns"}]}`

**Expect**:
- MCP tool returns an error (non-200 MCP response, or `isError: true` with
  a message mentioning `columns`).
- No new snapshot is persisted (check via `database-query` if available:
  `select id from snapshots order by id desc limit 3`, compare to pre-run).

Record the exact error message.

### Test 5 — Workbench isolation

- `workbench_slug`: `qa-other`
- `view_type`: `table`
- `title`: `Isolation test`
- 2 rows per fixture.

**Expect**: new `snapshot_id`, unrelated to `qa-suite`.

---

## Report format

At the end, print:

1. Results table: test number, ✅/❌, snapshot_id, snapshot_slug,
   revision, workbench URL.
2. Requirements table mapping each spec ID (REQ-M1-001..014) to
   pass / fail / not-covered. Not-covered for this plan:
   002, 004, 006, 009, 012, 013.
3. Flagged regressions (any REQ that passed in the prior run and now
   fails).

## Prior-run baseline (2026-04-19)

- ✅ 001, 003, 005, 007, 010, 014
- ❌ 008 (schema accepted missing `columns`)
- ❌ 011 (100 rows rendered in SSR, no truncation footer)

Any new ❌ on 001/003/005/007/010/014 is a regression — call it out
loudly.

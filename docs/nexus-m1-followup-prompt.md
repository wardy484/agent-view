# Nexus M1 — Follow-up Fix + Re-test Prompt

Paste the block below into a fresh Claude Code session at the repo root.

---

## Context

Prior QA run against the deployed `present_structured_data` MCP tool
(nexus-prod, https://nexus-ui-production-w1o1a4.laravel.cloud) against
`docs/nexus-spec.md` surfaced two failing requirements:

- **REQ-M1-008** — `TableViewSchema::validate()` must reject payloads missing
  `columns` (and `rows`) with a clear error surfaced through MCP. Currently a
  payload of `{"rows":[...]}` with no `columns` is accepted, persisted, and
  returns HTTP 200 with a new snapshot. Repro snapshot:
  `/workbenches/qa-suite/snapshots/01KPJH2KAYQW7A1ZWHFSQGXJ59`.
- **REQ-M1-011** — `TablePreviewRenderer` must truncate to 50 rows and show a
  `showing 50 of N` footer (HTML ≤ 64 KB). Currently a 100-row payload renders
  all 100 rows in SSR and no footer is emitted. Repro snapshot:
  `/workbenches/qa-large/snapshots/01KPJH21CDKCKZFWAPMKX4X0DH`.

Passing requirements from the last run (keep green):
001, 003, 005, 007, 010, 014. Untested: 002, 004, 006, 009, 012, 013.

## Task

1. Fix **REQ-M1-008**
   - Locate `TableViewSchema` (likely `app/Nexus/ViewSchemas/TableViewSchema.php`
     or similar). Ensure `validate()` throws a schema error when `columns` OR
     `rows` is missing, empty, or not an array.
   - Ensure the error propagates out of the MCP tool handler as a tool error
     (non-200 MCP response with message), not a persisted snapshot. Verify the
     `PresentStructuredData` MCP action calls the validator BEFORE
     `SnapshotVersioning::append()`.
   - Add a Pest feature test under `tests/Feature/Nexus/` covering:
     missing `columns`, missing `rows`, empty `rows`, non-array `columns`.

2. Fix **REQ-M1-011**
   - Locate `TablePreviewRenderer`. Cap rendered rows at 50; when
     `count(rows) > 50`, append a footer row/cell with text matching
     `showing 50 of {N}`. Keep HTML ≤ 64 KB (assert in test).
   - Unit test with 49, 50, 51, and 500 row inputs.

3. Run `vendor/bin/pint --dirty --format agent` then
   `php artisan test --compact --filter=Nexus`.

4. Deploy to Laravel Cloud (or note the deploy command for the user to run).

## Re-test system

After deploy, re-run the E2E suite. Use the MCP tool directly
(`mcp__nexus-prod__present_structured_data`) — do not curl.

A canonical re-test prompt lives in this repo at
`docs/nexus-m1-retest.md` (create it if missing — copy the 5-test plan
from the original QA session, keep fixture shapes identical so snapshot
diffs are meaningful).

Expected results after the fix:

| Test | Expected |
|------|----------|
| 1 Happy path | ✅ revision=1, HTTP 200, rows rendered |
| 2 Append | ✅ same slug, revision=2 |
| 3 100 rows | ✅ HTTP 200, HTML contains `showing 50 of 100`, `Customer 51` NOT present in SSR HTML |
| 4 Missing columns | ✅ MCP tool returns error; no new snapshot row in DB |
| 5 Isolation | ✅ qa-other gets its own snapshot_id |

Post every new workbench URL back to the user inline as you go so they
can eyeball the UI. Flag any REQ that still fails in a summary table at
the end.

## Output format

- Diff summary of changed files
- Pest test output (compact)
- Deploy status
- Re-test table (5 rows) with URLs
- Any remaining failing REQs

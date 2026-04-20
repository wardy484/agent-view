# M5 — Report (Markdown + Embedded Snapshots)

Introduces `view_type = "report"` — a narrative bundle of markdown prose
and inline embeds of other snapshots (table / kanban / flowchart /
slide_deck). Reports are *as-of-today* documents: each embed is pinned to
the embedded snapshot's current revision at the moment the report is
written, so readers see a stable view even if the underlying data
churns.

## Locked design decisions

1. **Pin on write.** Embed revisions are frozen at `append()` time inside
   the same transaction that creates the report revision, so concurrent
   appends on the embedded snapshot can't leave dangling pins.
2. **Transitive read.** Sharing a report grants read on its embeds via a
   policy extension — no share-row materialisation, so revocation and
   report-edit both reflow for free.
3. **Locator = `snapshot_id` (int FK).** Stable, already returned by
   `present_structured_data` for agents to chain.

## REQs landed (9/9)

| REQ | Summary |
|---|---|
| REQ-M5-000 | Register `view_type = "report"` end-to-end (MCP tool + dashboard sample slot + dispatcher stub). |
| REQ-M5-001 | `ReportViewSchema::validate()` accepts `{ blocks: [...] }` discriminated union with dot-path error messages. |
| REQ-M5-002 | Schema rejects cross-workbench embeds and nested reports. |
| REQ-M5-003 | `SnapshotVersioning::append()` materialises one `snapshot_embeds` row per embed, pinned to the embedded snapshot's `current_version_id`. |
| REQ-M5-004 | `SnapshotPolicy@view` grants transitive read when the caller can view a report whose *current* revision embeds the snapshot. |
| REQ-M5-005 | MCP writes remain workbench-owner only, even on snapshots only transitively readable via REQ-M5-004. |
| REQ-M5-006 | `ReportPreviewRenderer` — 64 KB byte-budgeted HTML preview with per-embed byte budgeting and a binary-search-style trim loop (O(log N) under pathological payloads). |
| REQ-M5-007 | `SnapshotController@show` inlines `resolved_blocks` into the Inertia version payload so the React renderer doesn't fan out N HTTP calls. |
| REQ-M5-008 | `resources/js/components/nexus/report-view.tsx` dispatches markdown blocks through react-markdown and embed blocks to the existing view components in a read-only wrapper with a "v{pinned} · current v{latest}" badge. |

## Gates

- `php artisan spec:check` → **73/73**
- `./vendor/bin/pest --compact` → **253 passed (1049 assertions)**
- `./vendor/bin/pint --dirty --format agent` → clean
- `pnpm run build` → clean (snapshot bundle ~245 KB gzip 73 KB)
- `npx eslint resources/js/components/nexus/report-view.tsx resources/js/pages/snapshot.tsx` → clean

Pre-existing `npx tsc --noEmit` errors are all on `auth` Inertia shared
prop typing and unrelated to this milestone.

## Design details worth calling out

- **Stale pins auto-filter.** `snapshot_embeds.report_version_id` joins
  on `snapshots.current_version_id` — when a report gets a new revision,
  old pin rows go idle (not deleted) and stop contributing to transitive
  reads automatically.
- **Re-entrancy guard in the policy.** `SnapshotPolicy@view` uses a
  static `$transitiveStack` to prevent cycles even though REQ-M5-002
  prohibits nested reports — defence in depth in case future work allows
  a shallow report-embeds-report.
- **Cross-workbench validation happens BEFORE workbench creation** so a
  failed validation leaves zero side-effects — the controller looks up
  `$existing` workbench first and threads `$existing?->id` into the
  schema.
- **Renderer trim loop is O(log N)**, not O(N²) — a prior iteration
  burned 16 seconds on a 5000-block pathological payload before the
  binary-search fix.
- **Selection inside reports is disabled for M5.** The embed wrapper
  is marked `aria-readonly` and `data-report-embed-readonly="true"`;
  child selection affordances bubble up into a no-op. Re-enabling
  selection in reports is a follow-up.

## Manual probe

`demo/m5.sh` exercises:
1. Publish two table snapshots via MCP.
2. Publish a report embedding both inline.
3. Bump one embedded snapshot — the report still shows the pinned
   revision with a `current v{n+1}` stale badge.
4. (Manual) Share the report with a second user — both embeds render;
   direct navigation to each embed URL also works (transitive grant).
5. (Manual) Revoke the share — all three URLs 403 on next request.

## Follow-ups parked for later milestones

- Selection / "Send to Agent" affordances inside report mode.
- Cross-workbench embeds (explicitly out of M5 scope).
- A future hard-delete path for embed targets will want a scheduled
  sweep of `snapshot_embeds` rows whose `embedded_snapshot_id` is gone;
  the controller already degrades gracefully to `{restricted: true}` in
  that case.

## Touched

**Created** (10 files)
- `app/Nexus/Schemas/ReportViewSchema.php`, `ReportViewSchemaException.php`
- `app/Nexus/Renderers/ReportPreviewRenderer.php`
- `resources/js/components/nexus/report-view.tsx`
- `database/migrations/2026_04_20_141601_create_snapshot_embeds_table.php`
- `tests/Feature/Nexus/{ReportDispatcherTest,ReportViewSchemaTest,ReportEmbedValidationTest,ReportRevisionPinningTest,ReportTransitivePolicyTest,ReportOwnerWriteTest,ReportPreviewRendererTest,ReportEmbedResolutionTest,ReportDispatcherFrontendTest}.php`
- `demo/m5.sh`

**Modified**
- `docs/nexus-spec.md` (appended §M5 with 9 REQ bullets)
- `app/Nexus/SnapshotVersioning.php`, `app/Policies/SnapshotPolicy.php`, `app/Http/Controllers/SnapshotController.php`
- `app/Mcp/Tools/PresentStructuredData.php`
- `resources/js/pages/snapshot.tsx`, `resources/js/pages/dashboard.tsx`
- `AGENTS.md` (fixed component and Schema/Renderer path references in "How to Add a New view_type")

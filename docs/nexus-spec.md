# Nexus-UI — Requirements Specification

> This document is the source of truth for what Nexus-UI must do.
> Every atomic requirement has a stable `REQ-*` ID.
> IDs are **never reused and never renumbered** — traceability depends on it.
>
> Agents and humans close requirements by writing a Pest test whose
> `it('REQ-XXX-NNN: …')` name references the ID. `php artisan spec:check`
> enforces 1:1 correspondence in CI.
>
> See `AGENTS.md` for the 7-step delivery loop.
> See `/Users/kim/.claude/plans/the-prompt-role-you-cozy-goblet.md` for the full
> PRD, architecture, and rationale behind these requirements.

---

## Phase 0A — Walking Skeleton Deploy

- **REQ-P0A-001** `.env.example` uses Postgres (not SQLite) as the default DB connection.
- **REQ-P0A-002** `.env.example` uses Redis for session, cache, and queue drivers.
- **REQ-P0A-003** `cloud.yaml` exists and defines build + deploy steps for Laravel Cloud.
- **REQ-P0A-004** `.herd.yml` pins the PHP and Node versions the project expects locally.
- **REQ-P0A-005** Laravel Cloud production environment deploys green from `main`.
- **REQ-P0A-006** Laravel Cloud production environment runs `php artisan migrate --force` automatically.
- **REQ-P0A-007** Laravel Cloud queue worker process is declared and running.
- **REQ-P0A-008** No secrets are committed to the repository (`.env` is gitignored; secrets live in Cloud env config).

## Phase 0B — Delivery Infrastructure

- **REQ-P0B-001** `php artisan spec:check` exits non-zero when a REQ-ID in `docs/nexus-spec.md` has no matching Pest test.
- **REQ-P0B-002** `php artisan spec:check` exits zero when every REQ-ID in `docs/nexus-spec.md` has a matching Pest test.
- **REQ-P0B-003** `php artisan spec:check --next` prints the first unfulfilled REQ-ID (filtered by `--milestone=…` when supplied) and exits zero.
- **REQ-P0B-004** `AGENTS.md` exists at repo root with the 7-step agent contract, directory map, command list, and worktree rule.
- **REQ-P0B-005** `.github/pull_request_template.md` requires listing REQ-IDs, tests added, demo steps, and spec-change flag.
- **REQ-P0B-006** GitHub Actions CI runs `spec:check`, `php artisan test`, `pint --test`, `pnpm lint`, and `pnpm type-check`; any failure blocks merge.
- **REQ-P0B-007** `scripts/worktree-bootstrap.sh <branch>` creates a git worktree, provisions a per-branch Postgres DB, writes the worktree's `.env`, and installs deps idempotently.
- **REQ-P0B-008** `scripts/worktree-destroy.sh <branch>` drops the per-branch Postgres DB, flushes the branch's Redis prefix, and removes the worktree.
- **REQ-P0B-009** `scripts/create-issues.php` reads this spec and creates an issue per REQ-ID, idempotent (skips existing issues).
- **REQ-P0B-010** `demo/common.sh` exposes reusable helpers (reset DB, mint token, call MCP) used by every `demo/m*.sh`.

## M1 — Universal Table

- **REQ-M1-001** MCP tool `present_structured_data` accepts the v1 input schema (workbench_slug, view_type, data_payload, optional snapshot_id, title, metadata).
- **REQ-M1-002** All `/ai/*` routes require a valid Sanctum Bearer token; anonymous requests return 401.
- **REQ-M1-003** `snapshot_versions.revision` is monotonic (1, 2, 3…) and unique per `snapshot_id`.
- **REQ-M1-004** `present_structured_data` response contains `structuredContent`, a text content part with the workbench URL, and a `text/html` resource content part.
- **REQ-M1-005** Table view component renders every row in `data_payload.rows` using columns from `data_payload.columns`.
- **REQ-M1-006** TanStack Table performs filter, multi-column sort, and pagination client-side with zero network calls.
- **REQ-M1-007** The version switcher lists every revision of a snapshot, newest first, and navigates to the selected revision.
- **REQ-M1-008** `TableViewSchema::validate()` rejects payloads missing `columns` or `rows` with a clear error.
- **REQ-M1-009** `SnapshotVersioning::append()` is the only writer to `snapshot_versions`; direct model writes are disallowed by a test.
- **REQ-M1-010** A newly-created snapshot has `current_version_id` equal to its first version; appending a version updates `current_version_id`.
- **REQ-M1-011** `TablePreviewRenderer` produces HTML ≤ 64 KB, truncates to 50 rows, and shows a "showing 50 of N" footer when truncation occurred.
- **REQ-M1-012** `preview_html` is cached on the `snapshot_versions` row at write-time; repeated reads never re-render.
- **REQ-M1-013** Users can mint a Sanctum personal access token in `/settings/tokens` scoped to a `workbench_slug`.
- **REQ-M1-014** The workbench page URL returned by the tool resolves to a page that renders the table in the browser.

## M2 — Multi-view + Search

- **REQ-M2-001** MCP tool accepts `view_type: "kanban"` and validates `{columns, cards}` shape.
- **REQ-M2-002** Kanban view renders columns and cards from `data_payload`.
- **REQ-M2-003** `KanbanPreviewRenderer` produces static HTML within the 64 KB cap.
- **REQ-M2-004** MCP tool accepts `view_type: "flowchart"` and validates `{mermaid_source}` shape.
- **REQ-M2-005** Flowchart view renders Mermaid source via Mermaid.js in the browser.
- **REQ-M2-006** `FlowchartPreviewRenderer` converts Mermaid source to SVG server-side for inline preview.
- **REQ-M2-007** Table view fuzzy-search matches across all column values without a network call.
- **REQ-M2-008** Table view supports multi-column sort (shift-click adds secondary/tertiary sort keys).
- **REQ-M2-009** Table view column filters persist in the URL so views are shareable.

## M3 — Full Workbench

- **REQ-M3-001** MCP tool accepts `view_type: "slide_deck"` and validates `{slides: [{title, body_md}]}` shape.
- **REQ-M3-002** Slide deck view supports keyboard navigation (←/→ arrows, Space).
- **REQ-M3-003** MCP tool `get_follow_up_context` returns unconsumed `follow_up_contexts` rows for a workbench and marks them `consumed_at`.
- **REQ-M3-004** Selecting rows/nodes in the UI and clicking "Send back to Agent" creates a `follow_up_contexts` row.
- **REQ-M3-005** `get_follow_up_context` is idempotent: a consumed row is never returned twice.
- **REQ-M3-006** Every MCP call writes a `mcp_call_logs` row with tool_name, duration_ms, status, payload_bytes.
- **REQ-M3-007** The "Agent Activity" dashboard is a singleton snapshot per workbench rendered via `snapshot.tsx` — no bespoke page.
- **REQ-M3-008** `present_structured_data` response includes a `ui://` resource with an iframe-embeddable URL for `mcp-ui`-aware clients.
- **REQ-M3-009** `VersionDiff::between($a, $b)` returns added/removed/changed rows for any two versions of a table snapshot.
- **REQ-M3-010** `SnapshotController` exposes a `mode` Inertia prop equal to `"preview"` when the request has `?mode=preview` or no authenticated user, and `"app"` otherwise; an `isAuthenticated` boolean prop reflects the auth state.
- **REQ-M3-011** The `ui://` resource emitted by `present_structured_data` carries `?mode=preview` on the iframe-embeddable URL so mcp-ui clients render snapshots without the app shell.
- **REQ-M3-012** The `snapshot` page renders inside the app sidebar layout when `mode === "app"` and renders bare (no sidebar, no workbench header) with a subtle floating home link when `mode === "preview"`.
- **REQ-M3-013** In-app mode shows a fullscreen toggle button in the snapshot header; activating it hides the workbench header and surfaces the same floating home link, and the Escape key restores the chrome.

## M4 — Sharing

- **REQ-M4-000** `workbenches` gains a nullable `owner_user_id` foreign key to `users` (`nullOnDelete`). When `PresentStructuredData` runs under Sanctum web auth (`auth()->user()` present), it sets `owner_user_id` on workbench creation. When it runs under the unauthenticated local stdio server, `owner_user_id` stays null and the workbench is treated as system-owned (never shareable). A one-shot data migration back-fills existing rows from the earliest `mcp_call_logs` entry whose `user_id` is non-null. Sharing policies (REQ-M4-005) reject every workbench whose owner is null. Once set to a non-null user, `owner_user_id` never changes except via an explicit transfer action (out of scope for M4).
- **REQ-M4-001** Snapshots carry a `visibility` enum with values `private` (default), `link`, and `shared`. Visibility is stored on the `snapshots` row, not the workbench. A viewer always sees the snapshot's **latest** revision (resolved via `current_version_id` at request time); earlier revisions remain hidden to non-owners.
- **REQ-M4-002** When `visibility = link`, the snapshot has a 32-byte URL-safe random `share_token` (unique, indexed). Route `GET /s/{token}` resolves to the read-only snapshot view without authentication. Flipping visibility to `private` clears the token; re-enabling `link` mints a fresh token. The route is rate-limited and every hit writes a `snapshot_share_accesses` audit row.
- **REQ-M4-003** Table `snapshot_shares` with columns `id`, `snapshot_id`, `email` (lowercased, indexed), `user_id` nullable, `granted_by_user_id`, `created_at`, `revoked_at` nullable. Unique `(snapshot_id, email)` where `revoked_at IS NULL`.
- **REQ-M4-004** Sharing a snapshot by email: if a `User` with that email exists, `user_id` is populated immediately and a `SnapshotSharedNotification` is queued. If no user exists, the row is created with `user_id = null` and an invite email with a signup link is sent; Fortify's `CreateNewUser` back-fills `user_id` on any matching `snapshot_shares` rows during registration.
- **REQ-M4-005** `SnapshotPolicy@view` grants read access when any of the following is true: the user owns the workbench; `visibility = link` and the request presents the valid `share_token`; `visibility = shared` and an unrevoked `snapshot_shares` row matches the authenticated user's id or email. Revoking sets `revoked_at` (soft — never hard-deleted).
- **REQ-M4-006** The shared snapshot view is strictly read-only: version switcher is hidden (latest-only per REQ-M4-001), the "Send back to Agent" control is hidden for public-link viewers, and all mutation affordances (rename, delete, re-share) are gated by ownership.
- **REQ-M4-007** Signed-in users see a dedicated **Shared with me** sidebar section listing every snapshot where they hold an unrevoked `snapshot_shares` row (resolved by `user_id` or email). Owner sidebars display a share-count badge on snapshots with active shares or an active `share_token`.
- **REQ-M4-008** MCP writes (`present_structured_data`) remain owner-only regardless of share state; shares grant read access only. `get_follow_up_context` is scoped to the caller's Sanctum token, so each viewer's selections are visible only to that viewer's agent — never to the owner or other viewers.
- **REQ-M4-009** `Snapshot::shareToken()` rotation and `snapshot_shares` revocation both invalidate any cached access decisions within one request cycle (no stale policy cache).
- **REQ-M4-010** Owner-facing **Share** control on the snapshot page header. Rendered only when `is_owner && !is_public_link`. Opens a dialog that surfaces three visibility modes (Private / Anyone with link / Specific people), the current `/s/{token}` URL with a copy button when link mode is active, and an email input plus revoke-able list of current shares when shared mode is active. All four mutation endpoints (`PATCH .../visibility`, `POST .../share-token/rotate`, `POST .../shares`, `DELETE .../shares/{share}`) live under the authenticated snapshot route prefix and reject any non-owner caller with 403. The dialog reads the current state from `shares` and `share_url` props included on the snapshot show payload (owners only).

## M5 — Report

- **REQ-M5-000** `view_type = "report"` is registered end-to-end: the `present_structured_data` MCP tool accepts it, the React snapshot dispatcher (`resources/js/pages/snapshot.tsx`) routes it to the report renderer, and the dashboard (`resources/js/pages/dashboard.tsx`) lists it under a `narrative` zone alongside the four structured zones. Unknown-view-type fallback behaviour is preserved for any future type.
- **REQ-M5-001** `ReportViewSchema::validate()` accepts `{ blocks: [...] }` where each block is a discriminated union: `{type: "markdown", body: string}` (markdown prose) or `{type: "embed", snapshot_id: int}` (embedded snapshot). Blocks is a non-empty array; validation throws `ReportViewSchemaException` with dot-path messages (e.g. `data_payload.blocks[3].snapshot_id is required and must be an integer.`).
- **REQ-M5-002** Embeds are same-workbench only and cannot target another report. The MCP tool resolves each `snapshot_id` in the payload against the target workbench; mismatched-workbench embeds and embeds whose target snapshot currently has `view_type === "report"` are rejected with a dot-path error before any `snapshot_versions` row is written.
- **REQ-M5-003** Revision pinning on write. When `SnapshotVersioning::append()` persists a `report` view, every embed block is rewritten so that `snapshot_version_id` is materialised to the embedded snapshot's `current_version_id` at the moment of append. The pin is frozen for that report revision; a later report revision re-pins at that point in time. The write also populates a `snapshot_embeds` denorm row per (report_snapshot_id, report_revision, embedded_snapshot_id, embedded_snapshot_version_id).
- **REQ-M5-004** `SnapshotPolicy@view` grants transitive read access to an embedded snapshot when the caller can view at least one report whose **latest** revision embeds that snapshot. Works for owner viewers, `snapshot_shares` recipients, and valid `share_token` link viewers on the report. Revoking the report share, or a later report revision dropping the embed, automatically revokes transitive access on the next request (no materialised share rows to clean up).
- **REQ-M5-005** MCP writes remain owner-only regardless of transitive read access — a viewer who can read an embedded snapshot only because a report transitively granted access cannot call `present_structured_data` against that snapshot's workbench. M4-008 stands untouched.
- **REQ-M5-006** `ReportPreviewRenderer::render()` emits HTML that renders markdown blocks as escaped prose and embed blocks as summary cards (title + view_type badge + truncated child preview HTML). Enforces the existing 64 KB byte cap by allocating `floor((65536 - markdown_bytes) / count(embed_blocks))` per embed and truncating child preview HTML to fit; oversize output carries a `data-truncated="true"` footer. The root wrapper is `<div data-nexus-preview="report">`.
- **REQ-M5-007** The snapshot page data loader (`SnapshotController@show`) resolves every embed in a `report` version at read time: loads the pinned `SnapshotVersion`, runs `SnapshotPolicy@view` per embed, and inlines `{view_type, payload, version_id, title, pinned_revision, current_revision, restricted}` per embed block into the Inertia props. Policy-denied embeds (rare given REQ-M5-004) reduce to `{type: "embed", restricted: true}`; deleted embed targets produce the same `restricted` shape. Markdown blocks pass through unchanged.
- **REQ-M5-008** `resources/js/components/nexus/report-view.tsx` renders blocks in order: markdown via the shared markdown pipeline (reused from `slide-deck-view.tsx`), embeds via the existing `TableView` / `KanbanView` / `FlowchartView` / `SlideDeckView` components inside a read-only wrapper. Each embed card displays a "v{pinned} · current v{latest}" badge that links to the embedded snapshot in a new tab, a `Restricted embed` placeholder when `restricted = true`, and disables selection / send-to-agent affordances inside report mode for M5.

---

## Requirement ID Rules

1. **Format**: `REQ-<scope>-<nnn>` where scope ∈ `{P0A, P0B, M1, M2, M3, M4, …}` and `nnn` is a zero-padded 3-digit number.
2. **Stability**: once issued, an ID's text may be refined but its number is frozen forever.
3. **One test per ID**: any Pest `it(…)` name must match the regex `/REQ-[A-Z0-9]+-\d{3}/` to count.
4. **Additions require a spec PR**: if a requirement is missing, add it here before writing code.

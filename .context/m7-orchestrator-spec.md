# M7 — Orchestrator (draft spec)

> Draft milestone supporting the `/feature` skill (`.context/feature-skill.md`).
> Paste these paragraphs into `docs/nexus-spec.md` after grilling confirms scope.
> Next free milestone is M7 (M1–M6 are taken).

## M7 — Orchestrator support

The `/feature` skill orchestrates parallel subagents through milestone delivery
while visualising progress in a Nexus workbench. M7 closes four gaps that
prevent the kanban from being a usable live source of truth: stable card IDs,
real-time UI updates, orchestration metadata, and richer cards. Without M7,
the orchestrator can publish kanban revisions but the user cannot see them
update live, anchors drift across edits, and resume loses scout state.

### REQ-M7-001: stable card IDs with server-side carry-forward

`KanbanViewSchema` accepts an optional `id` per card today, but unlike
`ReportViewSchema` it does not carry IDs forward across revisions.
REQ-M7-001 makes the schema (a) auto-assign a UUID `id` to any card that
omits one, and (b) on subsequent revisions of the same snapshot, match
incoming cards against the previous revision's cards by explicit `id`
first, falling back to a `(column_key, title)` tuple match — assigning
the previous `id` on match. Carry-forward is best-effort; explicit IDs
remain the reliable path. Expose the assigned IDs in the `validate()`
return value so callers can round-trip them.

### REQ-M7-002: live snapshot updates over Reverb

`resources/js/pages/snapshot.tsx` currently renders a single revision and
does not refresh when a new revision is appended. REQ-M7-002 broadcasts a
`SnapshotVersionAppended` event on the `snapshot.{snapshot_id}` private
channel from `App\Nexus\SnapshotVersioning::append()`, and subscribes the
snapshot page to that channel via Laravel Echo. On event receipt, the
page re-fetches the latest revision via Inertia partial reload and
re-renders with a brief animated transition. Authorisation: only the
workbench owner and active `snapshot_shares` grantees may subscribe.

### REQ-M7-003: orchestrator metadata blob on snapshot revisions

`snapshot_versions` rows currently carry `metadata.summary` (≤120 chars,
human-facing). REQ-M7-003 adds an optional `metadata.orchestrator` JSON
object — a free-form blob the `/feature` orchestrator uses to persist
scout outputs, dependency DAG, in-flight subagent IDs, and last failure
reason. The blob is not rendered in the workbench UI but is returned
verbatim by `get_snapshot_comments` and the snapshot read endpoints so
that `/feature --resume` can reconstruct full state. Schema validation
accepts any JSON-serialisable shape; the orchestrator owns its own
contract.

### REQ-M7-004: card link, status, and assignee fields

REQ-M7-004 extends the kanban card schema with three optional fields:
`link_url` (string URL — clickable card surface), `status` (enum
`ok|warn|error` — drives card border colour: green / amber / red), and
`assignee` (string — subagent ID or "human", rendered as a small badge).
All three are optional and back-compatible. The React kanban view
renders the link as the card's title anchor, the status as a left
border stripe in the corresponding colour, and the assignee as a chip
in the card footer. None of these fields participate in carry-forward
matching (REQ-M7-001 still uses `id` and `(column_key, title)` only).

---

## Out of scope (deferred)

- **Workbench archive state** — useful but doesn't block the skill;
  defer to a future milestone.
- **Card timestamps in UI** — version history already records this;
  surfacing is a pure frontend tweak that can ride a later PR.
- **GitHub PR webhook → kanban auto-update** — large lift; orchestrator
  polling works fine for a toy project.

## Dependency notes

- REQ-M7-001 has no deps. Touch only `KanbanViewSchema` + tests.
- REQ-M7-002 depends on Reverb being installed (verify with
  `composer show laravel/reverb`); otherwise add it first. Touches
  `SnapshotVersioning`, channel routes, `snapshot.tsx`.
- REQ-M7-003 has no deps; pure schema/migration work.
- REQ-M7-004 has no hard deps but should land *after* REQ-M7-001 so
  carry-forward semantics apply to the new fields too. Touches
  `KanbanViewSchema` and the React kanban view.

REQ-M7-001 and REQ-M7-003 are fully disjoint and can run in parallel.
REQ-M7-002 touches `SnapshotVersioning` so it must run sequentially with
anything else hitting that file. REQ-M7-004 should wait for REQ-M7-001.

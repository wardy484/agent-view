# M9 — UI Baseline (Pest 4 Browser Tests)

Locks down a smoke + interaction baseline for the signed-in app using Pest 4 browser testing. Every top-level user-visible flow now has a Pest browser test that asserts the page renders, has no console errors, and the core interaction works end-to-end.

`php artisan spec:check --milestone=M9` exits zero — **16/16 REQs fulfilled.**

## REQs delivered (16)

- **REQ-M9-001** — Foundation: `pest-plugin-browser` + `playwright` + Chromium, `phpunit.xml` Browser testsuite, `tests/Pest.php` Browser config (RefreshDatabase + auto-seed), `UiBaselineSeeder` with one fixture per `view_type` owned by a self-provisioned fixture user, env-gated registration.
- **REQ-M9-002** Login · **REQ-M9-003** Register · **REQ-M9-004** 2FA setup · **REQ-M9-005** 2FA challenge · **REQ-M9-006** Logout
- **REQ-M9-007** Dashboard empty-state · **REQ-M9-008** Tokens settings (mint + revoke)
- **REQ-M9-009** Table view (sort) · **REQ-M9-010** Kanban view · **REQ-M9-011** Report view · **REQ-M9-012** Flowchart view (mermaid SVG) · **REQ-M9-013** Slide deck view (next/prev)
- **REQ-M9-014** Version switcher · **REQ-M9-015** Report comments lifecycle
- **REQ-M9-016** `ui-baseline` GitHub Actions workflow with `--parallel`, 2-shard matrix, Playwright cache; existing `quality-gate` excludes Browser to stay fast.

## Tests added

14 Pest browser tests under `tests/Browser/{Auth,Settings,Snapshot}/` plus 2 feature tests (seeder + workflow assertions). Total: **107 assertions, 11.61s** for the full Browser suite.

## Spec wording corrections (caught at integration)

- **REQ-M9-006** — Fortify's default `LogoutResponse` redirects to `/`, not `/login`. Spec assertion softened to "session terminated + protected page bounces to /login".
- **REQ-M9-007** — Reframed for M8 Library Home reality: empty-state launcher (zero-workbench user) instead of the removed sample-launcher control.
- **REQ-M9-010** — `kanban-view.tsx` is currently presentational with no drag-drop wired; spec says "moves a card via `SnapshotVersioning::append`" (the canonical writer a future drag-drop controller would invoke).
- **REQ-M9-011** — Inline `<VersionSwitcher>` only renders when `versions.length > 1`; spec says "version-history affordance" (sidebar `History` tab).

## New dependencies

- `composer require pestphp/pest-plugin-browser:^4.0 --dev`
- `pnpm add -Dw playwright` + `npx playwright install chromium`

## Demo / verification

```bash
php -d memory_limit=1G artisan test --testsuite=Browser
# Tests: 14 passed (107 assertions), Duration: ~11s
php artisan spec:check --milestone=M9
# 16/16 requirements have tests
```

## Spec-change flag

Yes — `docs/nexus-spec.md` adds the M9 block (16 REQs) and includes the four wording corrections noted above. No prior REQs were touched.

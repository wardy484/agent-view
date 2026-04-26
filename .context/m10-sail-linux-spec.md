# M10 — Linux dev parity via Sail (draft spec)

> Draft milestone giving Linux contributors (and Polyscope-managed
> Linux workspaces) first-class dev parity with the existing Mac/Herd
> path. Mac contributors keep Herd; Linux gets Sail. Postgres + Redis
> remain shared across worktrees on a single host; each worktree owns
> its own database inside the shared container.
>
> Paste these paragraphs into `docs/nexus-spec.md` after the user
> approves. Next free milestone is M10 (M1–M9 are taken).

## M10 — Linux dev parity via Sail

> Polyscope spins up many parallel git-worktree workspaces on a single
> host. Today this only works on Mac via Herd. M10 closes that gap by
> shipping a Sail-based Linux path that coexists with Herd on Mac:
> shared services (one Postgres + one Redis container at host level),
> per-worktree DB isolation inside the shared Postgres, deterministic
> hash-based ports, and a unified bootstrap script that branches on
> `uname`. Mac contributors keep their fast Herd workflow; Linux gets
> a working container stack with no port collisions and no Polyscope
> preview-URL changes (Polyscope reads the chosen port from a
> well-known file).
>
> REQs are dependency-ordered so every intermediate state has main
> green and `spec:check` shows monotonic progress.

- **REQ-M10-001** Install Sail as a dev dependency (`composer require laravel/sail --dev`) and run `php artisan sail:install` to publish a `compose.yaml`. The published file pins explicit image tags — `laravel.test` on PHP 8.4, `pgsql` on Postgres 16, `redis` on Redis 7 — matching production (Laravel Cloud) and CI versions. No `latest` tags. The `laravel.test` service runs Sail's bundled web server (nginx + PHP-FPM internally), not `php artisan serve`.
- **REQ-M10-002** A single host-wide services topology: one Postgres container and one Redis container, lifetime managed independently of any worktree. The bootstrap script (REQ-M10-004) ensures the host stack is running, creates a uniquely-named database `nexus_<workspace_slug>` inside the shared Postgres for each worktree, and writes the connection details into the worktree's `.env`. Teardown drops the database but leaves the container running so RAM usage stays flat regardless of worktree count.
- **REQ-M10-003** Deterministic per-workspace port assignment: the bootstrap script computes `APP_PORT = 20000 + (crc32(workspace_slug) % 10000)` and writes it to the worktree's `.env`. The same algorithm runs every time so a re-bootstrap of the same workspace gets the same port. Sail's `compose.yaml` reads `APP_PORT` for the `laravel.test` host binding so workspaces never collide. The script aborts with a clear error if the chosen port is already bound by something other than this workspace's stack.
- **REQ-M10-004** `scripts/worktree-bootstrap.sh` becomes platform-aware via `uname -s`. `Darwin` → existing Herd path (parked site + per-worktree Postgres on Herd's daemon, unchanged behaviour). `Linux` → new Sail path (ensure host services stack, create per-workspace DB, compute and validate port, write `.env`, `./vendor/bin/sail up -d`). Common steps (git worktree creation, `composer install`, `pnpm install`, `php artisan key:generate`, `migrate --seed`) are shared across both branches via helper functions. The script is idempotent on both platforms and supports `--help` printing usage, detected platform, host services stack location, and assigned port so agents can self-discover the workflow without reading the source.
- **REQ-M10-005** `scripts/worktree-destroy.sh` becomes symmetrically platform-aware. `Darwin` → unpark Herd site, drop the per-worktree DB on Herd's Postgres, `git worktree remove`. `Linux` → drop the per-workspace database from the shared container, `./vendor/bin/sail down` for that worktree's compose project, `git worktree remove`. The host services stack survives both. Re-running destroy on an already-destroyed worktree is a no-op.
- **REQ-M10-006** Octane runs inside the Sail `laravel.test` container. The published `compose.yaml` sets `SUPERVISOR_PHP_COMMAND=/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan octane:start --server=frankenphp --host=0.0.0.0 --port=80` so the container boots Octane directly with no `artisan serve`. Reverb runs in the same container (Supervisor or sibling service) with Redis as the broadcast driver. `OCTANE_HTTPS=false` since Polyscope handles TLS upstream.
- **REQ-M10-007** Vite runs on the host on both platforms — never inside Sail. HMR over a Docker volume mount is too slow and fragile to be the default. The bootstrap script writes `VITE_HOST=host.docker.internal` (Mac) or the host gateway IP (Linux) into the worktree's `.env` so the Sail container can reach the Vite dev server when rendering Inertia pages server-side. This REQ is configuration plus documentation; no new code.
- **REQ-M10-008** Polyscope preview port handoff: the bootstrap script writes the chosen `APP_PORT` to `.polyscope/preview-port` (a single line, integer) after assignment. The file is gitignored. Polyscope's workspace runner reads it to construct the preview URL. If the file is missing, Polyscope falls back to its default port-discovery so existing Mac/Herd workspaces remain unaffected.
- **REQ-M10-009** The full local gate (`php artisan test`, `vendor/bin/pint --dirty --format agent`, `pnpm lint`, `pnpm type-check`, `php artisan spec:check`) passes on a fresh Linux Sail bootstrap. CI itself is unchanged (still uses GH Actions service containers, faster than `docker compose up` in CI). This REQ proves the Sail dev path matches CI's expectations rather than introducing a new CI matrix.
- **REQ-M10-010** `AGENTS.md` (= `CLAUDE.md`) gains a "Linux dev (Sail)" section written for both humans and AI agents. It covers: Docker prerequisites, the host services stack lifecycle (start, stop, health-check command, where its `compose.yaml` lives), the unchanged `./scripts/worktree-bootstrap.sh <branch>` command, a "Sail command equivalents" cheat-sheet showing every artisan/composer/pnpm invocation prefixed with `./vendor/bin/sail` on Linux, the platform-detection idiom (`[ "$(uname -s)" = "Linux" ]`) so agents know which path they're on, and the `.polyscope/preview-port` contract from REQ-M10-008. The existing "Command Cheat Sheet" section is extended with the Sail equivalents inline. Two new entries land in the "Never Do" list: (a) "Never `docker compose down` the host services stack — it is shared across every worktree on this host" and (b) "Never assume Herd; detect the platform first." The "Always Ask Before Coding" list gets a new item: "Will this work on both Herd (Mac) and Sail (Linux) paths?"

---

## Dependency order

1. M10-001 (Sail installed + compose.yaml pinned) — prerequisite for everything else.
2. M10-003 (port assignment shell function) — pure shell, no deps.
3. M10-002 (shared services + per-workspace DB helper) — needs M10-001.
4. M10-006 (Octane in container) — needs M10-001.
5. M10-007 (Vite host wiring) — needs M10-001 (env var path).
6. M10-004 (bootstrap.sh Linux branch) — needs M10-002 + M10-003.
7. M10-005 (destroy.sh Linux branch) — needs M10-004.
8. M10-008 (Polyscope handoff) — needs M10-003 + M10-004.
9. M10-009 (gate green on Linux) — needs M10-004 + M10-006.
10. M10-010 (docs) — last; describes shipped state.

REQs 002, 003, 006, 007 are file-disjoint and parallelizable. 004/005 are tightly coupled — single worker. 010 lands after everything else is green.

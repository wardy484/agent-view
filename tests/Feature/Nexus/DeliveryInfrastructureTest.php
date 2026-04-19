<?php

declare(strict_types=1);

/*
 * Attestation tests for Phase 0A / Phase 0B — they verify that the
 * infrastructure files exist and contain the contractual content.
 * These keep the delivery process honest: if someone removes
 * `AGENTS.md` or strips the CI file down to nothing, the gate fails.
 */

function repoRoot(): string
{
    return dirname(__DIR__, 3);
}

function repoFile(string $relative): string
{
    return repoRoot().'/'.$relative;
}

// ─── Phase 0A ──────────────────────────────────────────────────────────────

it('REQ-P0A-001: .env.example uses Postgres as the default DB connection', function () {
    $env = file_get_contents(repoFile('.env.example'));
    expect($env)->toMatch('/^DB_CONNECTION=pgsql$/m');
});

it('REQ-P0A-002: .env.example uses Redis for session, cache, and queue', function () {
    $env = file_get_contents(repoFile('.env.example'));
    expect($env)
        ->toMatch('/^SESSION_DRIVER=redis$/m')
        ->toMatch('/^CACHE_STORE=redis$/m')
        ->toMatch('/^QUEUE_CONNECTION=redis$/m');
});

it('REQ-P0A-003: cloud.yaml defines build and deploy steps', function () {
    $yaml = file_get_contents(repoFile('cloud.yaml'));
    expect($yaml)
        ->toContain('build:')
        ->toContain('deploy:')
        ->toContain('composer install')
        ->toContain('npm run build');
});

it('REQ-P0A-004: .herd.yml pins PHP and Node versions', function () {
    $yaml = file_get_contents(repoFile('.herd.yml'));
    expect($yaml)
        ->toMatch('/^php:\s*"[0-9.]+"/m')
        ->toMatch('/^node:\s*"[0-9]+"/m');
});

it('REQ-P0A-006: cloud.yaml runs migrate --force on deploy', function () {
    $yaml = file_get_contents(repoFile('cloud.yaml'));
    expect($yaml)->toContain('php artisan migrate --force');
});

it('REQ-P0A-007: cloud.yaml declares a queue worker process', function () {
    $yaml = file_get_contents(repoFile('cloud.yaml'));
    expect($yaml)
        ->toContain('worker:')
        ->toContain('queue:work');
});

it('REQ-P0A-008: .gitignore includes .env so secrets cannot be committed', function () {
    $gitignore = file_get_contents(repoFile('.gitignore'));
    expect($gitignore)->toMatch('/^\.env$/m');
});

// ─── Phase 0B (non-meta) ───────────────────────────────────────────────────

it('REQ-P0B-004: AGENTS.md exists with the 7-step contract and worktree rule', function () {
    $agents = file_get_contents(repoFile('AGENTS.md'));
    expect($agents)
        ->toContain('7-Step Delivery Loop')
        ->toContain('worktree-bootstrap.sh')
        ->toContain('Never Do')
        ->toContain('spec:check --next');
});

it('REQ-P0B-005: PR template requires REQ-IDs, tests, demo steps, spec flag', function () {
    $tpl = file_get_contents(repoFile('.github/pull_request_template.md'));
    expect($tpl)
        ->toContain('Requirement(s)')
        ->toContain('Tests added')
        ->toContain('Demo steps')
        ->toContain('Spec changes');
});

it('REQ-P0B-006: CI pipeline runs spec:check, Pest, Pint, lint, and type-check', function () {
    $ci = file_get_contents(repoFile('.github/workflows/ci.yml'));
    expect($ci)
        ->toContain('spec:check')
        ->toContain('php artisan test')
        ->toContain('pint --test')
        ->toContain('pnpm lint:check')
        ->toContain('pnpm types:check');
});

it('REQ-P0B-007: worktree-bootstrap.sh provisions worktree, DB, env, and deps', function () {
    $script = file_get_contents(repoFile('scripts/worktree-bootstrap.sh'));
    expect($script)
        ->toContain('git worktree add')
        ->toContain('createdb')
        ->toContain('APP_URL')
        ->toContain('DB_DATABASE')
        ->toContain('REDIS_PREFIX')
        ->toContain('composer install')
        ->toContain('php artisan migrate');

    // Idempotence markers — script must check for existing state before recreating.
    expect($script)
        ->toContain('already exists, skipping');

    expect(is_executable(repoFile('scripts/worktree-bootstrap.sh')))->toBeTrue();
});

it('REQ-P0B-008: worktree-destroy.sh drops DB, flushes Redis prefix, removes worktree', function () {
    $script = file_get_contents(repoFile('scripts/worktree-destroy.sh'));
    expect($script)
        ->toContain('dropdb')
        ->toContain('redis-cli')
        ->toContain('git worktree remove');

    expect(is_executable(repoFile('scripts/worktree-destroy.sh')))->toBeTrue();
});

it('REQ-P0B-009: create-issues.php reads the spec and is idempotent', function () {
    $script = file_get_contents(repoFile('scripts/create-issues.php'));
    expect($script)
        ->toContain('docs/nexus-spec.md')
        ->toContain('gh issue create')
        ->toContain('listExistingIssueTitles')
        ->toContain('skip  ');
});

it('REQ-P0B-010: demo/common.sh exposes reusable helpers', function () {
    $script = file_get_contents(repoFile('demo/common.sh'));
    expect($script)
        ->toContain('demo_reset_db')
        ->toContain('demo_mint_token')
        ->toContain('demo_mcp_call');
});

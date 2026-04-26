<?php

declare(strict_types=1);

it('REQ-M10-005: worktree-destroy.sh supports --help and exits 0', function () {
    exec(base_path('scripts/worktree-destroy.sh').' --help 2>&1', $out, $code);

    expect($code)->toBe(0);

    $output = implode("\n", $out);
    expect($output)
        ->toContain('Usage')
        ->toContain('Darwin')
        ->toContain('Linux');
});

it('REQ-M10-005: destroy script branches on uname -s', function () {
    $contents = file_get_contents(base_path('scripts/worktree-destroy.sh'));

    expect($contents)
        ->toContain('uname -s')
        ->toMatch('/case\s+["\']?\$\{?PLATFORM/');
});

it('REQ-M10-005: Linux branch drops the DB inside the shared container, leaves the stack running', function () {
    $contents = file_get_contents(base_path('scripts/worktree-destroy.sh'));

    expect($contents)
        ->toContain('infra/host-services/compose.yaml')
        ->toMatch('/dropdb.*-U.*nexus/')
        ->toMatch('/sail.*down/i');

    // The host services stack itself MUST survive teardown.
    expect($contents)->not->toMatch('/host-services\.sh\s+down/');
});

it('REQ-M10-005: Darwin branch keeps the existing Herd path (dropdb against 127.0.0.1)', function () {
    $contents = file_get_contents(base_path('scripts/worktree-destroy.sh'));

    expect($contents)
        ->toMatch('/Darwin\)/')
        ->toContain('dropdb -h 127.0.0.1');
});

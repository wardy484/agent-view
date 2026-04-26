<?php

declare(strict_types=1);

it('REQ-M10-004: worktree-bootstrap.sh supports --help and exits 0', function () {
    exec(base_path('scripts/worktree-bootstrap.sh').' --help 2>&1', $out, $code);

    expect($code)->toBe(0);

    $output = implode("\n", $out);
    expect($output)
        ->toContain('Usage')
        ->toContain('Darwin')
        ->toContain('Linux');
});

it('REQ-M10-004: --help prints detected platform and host services compose path', function () {
    exec(base_path('scripts/worktree-bootstrap.sh').' --help 2>&1', $out);

    $output = implode("\n", $out);
    expect($output)
        ->toContain('Detected platform:')
        ->toContain('infra/host-services/compose.yaml');
});

it('REQ-M10-004: bootstrap script branches on uname -s', function () {
    $contents = file_get_contents(base_path('scripts/worktree-bootstrap.sh'));

    expect($contents)
        ->toContain('uname -s')
        ->toMatch('/case\s+["\']?\$\{?PLATFORM/');
});

it('REQ-M10-004: bootstrap script defines a Linux Sail path that uses assign-port.sh', function () {
    $contents = file_get_contents(base_path('scripts/worktree-bootstrap.sh'));

    expect($contents)
        ->toContain('assign-port.sh')
        ->toMatch('/sail.*up\s*-d/i');
});

it('REQ-M10-004: bootstrap script keeps the Darwin Herd path (.test sites + Herd Postgres)', function () {
    $contents = file_get_contents(base_path('scripts/worktree-bootstrap.sh'));

    expect($contents)
        ->toMatch('/Darwin\)/')
        ->toContain('.test');
});

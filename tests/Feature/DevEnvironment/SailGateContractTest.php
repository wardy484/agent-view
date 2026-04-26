<?php

declare(strict_types=1);

it('REQ-M10-009: every dev-environment shell script passes bash -n syntax check', function () {
    $scripts = [
        'worktree-bootstrap.sh',
        'worktree-destroy.sh',
        'host-services.sh',
        'assign-port.sh',
        'install-skills.sh',
    ];

    foreach ($scripts as $script) {
        $path = base_path("scripts/{$script}");
        expect(file_exists($path))->toBeTrue("{$script} missing");

        exec('bash -n '.escapeshellarg($path).' 2>&1', $out, $code);
        expect($code)->toBe(0, "{$script} failed bash -n: ".implode("\n", $out));
        $out = [];
    }
});

it('REQ-M10-009: Linux bootstrap path wires every M10 piece together', function () {
    $contents = file_get_contents(base_path('scripts/worktree-bootstrap.sh'));

    expect($contents)
        ->toContain('host-services.sh')
        ->toContain('assign-port.sh')
        ->toContain('infra/host-services/compose.yaml')
        ->toContain('vendor/bin/sail')
        ->toContain('.polyscope/preview-port');
});

it('REQ-M10-009: project ships compose files for both per-workspace and shared host services', function () {
    expect(file_exists(base_path('compose.yaml')))->toBeTrue();
    expect(file_exists(base_path('infra/host-services/compose.yaml')))->toBeTrue();
});

it('REQ-M10-009: phpunit.xml still pins DB_CONNECTION=pgsql for dev/prod parity', function () {
    $phpunit = file_get_contents(base_path('phpunit.xml'));

    expect($phpunit)->toMatch('/<env\s+name="DB_CONNECTION"\s+value="pgsql"\s*\/>/');
});

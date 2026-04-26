<?php

declare(strict_types=1);

it('REQ-M10-002: ships a host-services compose.yaml at infra/host-services/', function () {
    expect(file_exists(base_path('infra/host-services/compose.yaml')))->toBeTrue();
});

it('REQ-M10-002: host-services compose declares pgsql and redis pinned to 16 and 7', function () {
    $compose = file_get_contents(base_path('infra/host-services/compose.yaml'));

    expect($compose)
        ->toMatch('/image:\s*[\'"]?postgres:16[\'"]?/')
        ->toMatch('/image:\s*[\'"]?redis:7[\'"]?/');
});

it('REQ-M10-002: host-services compose pins a stable docker compose project name', function () {
    $compose = file_get_contents(base_path('infra/host-services/compose.yaml'));

    expect($compose)->toMatch('/^name:\s*nexus-host-services\s*$/m');
});

it('REQ-M10-002: host-services compose declares named volumes for persistence', function () {
    $compose = file_get_contents(base_path('infra/host-services/compose.yaml'));

    expect($compose)
        ->toMatch('/volumes:.*nexus-pgsql/s')
        ->toMatch('/volumes:.*nexus-redis/s');
});

it('REQ-M10-002: provides scripts/host-services.sh with up/down/status subcommands', function () {
    $script = base_path('scripts/host-services.sh');

    expect(file_exists($script))->toBeTrue();
    expect(is_executable($script))->toBeTrue();

    $contents = file_get_contents($script);
    expect($contents)
        ->toContain('up')
        ->toContain('down')
        ->toContain('status')
        ->toContain('infra/host-services/compose.yaml');
});

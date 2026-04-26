<?php

declare(strict_types=1);

it('REQ-M10-006: laravel.test runs Octane via SUPERVISOR_PHP_COMMAND', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)
        ->toContain('SUPERVISOR_PHP_COMMAND')
        ->toContain('octane:start')
        ->toContain('--server=frankenphp')
        ->toContain('--host=0.0.0.0');
});

it('REQ-M10-006: Octane HTTPS is disabled in compose.yaml (Polyscope terminates TLS upstream)', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)->toMatch('/OCTANE_HTTPS:\s*[\'"]?false[\'"]?/');
});

it('REQ-M10-006: laravel.test does not run php artisan serve', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)->not->toContain('artisan serve');
});

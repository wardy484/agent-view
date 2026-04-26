<?php

declare(strict_types=1);

it('REQ-M10-001: publishes a compose.yaml at the project root', function () {
    expect(file_exists(base_path('compose.yaml')))->toBeTrue(
        'compose.yaml is missing — run `php artisan sail:install --with=pgsql,redis`.'
    );
});

it('REQ-M10-001: pins the laravel.test image to PHP 8.4', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)->toMatch('/sail-8\.4\/app/');
});

it('REQ-M10-001: pins Postgres to 16 and Redis to 7', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)
        ->toMatch('/image:\s*[\'"]?postgres:16[\'"]?/')
        ->toMatch('/image:\s*[\'"]?redis:7[\'"]?/');
});

it('REQ-M10-001: contains no :latest image tags', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)->not->toMatch('/image:\s*[\'"]?[a-z0-9\-\/]+:latest[\'"]?/i');
});

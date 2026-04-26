<?php

declare(strict_types=1);

it('REQ-M10-007: compose.yaml does NOT declare a vite service', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    // Vite runs on the host (npm run dev), never in a container — HMR over
    // a docker volume mount is too slow to be the default.
    expect($compose)->not->toMatch('/^\s+vite:\s*$/m');
});

it('REQ-M10-007: .env.example documents VITE_HOST for Sail containers', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('VITE_HOST');
});

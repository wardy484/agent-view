<?php

declare(strict_types=1);

it('REQ-M10-004: polyscope-setup.sh supports Darwin and Linux database provisioning paths', function () {
    $contents = file_get_contents(base_path('scripts/polyscope-setup.sh'));

    expect($contents)
        ->toContain('PLATFORM="$(uname -s)"')
        ->toMatch('/Darwin\)/')
        ->toMatch('/Linux\)/');
});

it('REQ-M10-004: polyscope-setup.sh uses shared host-services credentials on Linux', function () {
    $contents = file_get_contents(base_path('scripts/polyscope-setup.sh'));

    expect($contents)
        ->toContain('infra/host-services/compose.yaml')
        ->toContain('DB_USERNAME "nexus"')
        ->toContain('DB_PASSWORD "nexus"')
        ->toContain('.polyscope/preview-port');
});

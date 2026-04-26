<?php

declare(strict_types=1);

it('REQ-M10-008: bootstrap.sh writes APP_PORT to .polyscope/preview-port on Linux', function () {
    $contents = file_get_contents(base_path('scripts/worktree-bootstrap.sh'));

    expect($contents)->toContain('.polyscope/preview-port');
});

it('REQ-M10-008: .polyscope is gitignored', function () {
    $gitignore = file_get_contents(base_path('.gitignore'));

    expect($gitignore)->toMatch('/^\.polyscope\b/m');
});

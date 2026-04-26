<?php

declare(strict_types=1);

it('REQ-M10-010: AGENTS.md has a Linux dev (Sail) section', function () {
    $agents = file_get_contents(base_path('AGENTS.md'));

    expect($agents)->toMatch('/^##\s+Linux dev \(Sail\)\s*$/m');
});

it('REQ-M10-010: AGENTS.md documents the platform-detection idiom and preview-port contract', function () {
    $agents = file_get_contents(base_path('AGENTS.md'));

    expect($agents)
        ->toContain('uname -s')
        ->toContain('.polyscope/preview-port');
});

it('REQ-M10-010: AGENTS.md cheat-sheet shows ./vendor/bin/sail prefix for Linux', function () {
    $agents = file_get_contents(base_path('AGENTS.md'));

    expect($agents)->toContain('./vendor/bin/sail');
});

it('REQ-M10-010: Never Do list bans tearing down the shared host services stack', function () {
    $agents = file_get_contents(base_path('AGENTS.md'));

    expect($agents)
        ->toMatch('/Never.*docker compose down.*host services/i')
        ->toMatch('/Never.*assume Herd/i');
});

it('REQ-M10-010: Always Ask Before Coding asks whether a change works on both paths', function () {
    $agents = file_get_contents(base_path('AGENTS.md'));

    expect($agents)->toMatch('/both Herd \(Mac\) and Sail \(Linux\)/');
});

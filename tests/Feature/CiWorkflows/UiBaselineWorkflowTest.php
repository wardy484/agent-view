<?php

it('REQ-M11-016: ui-baseline workflow exists with sharded matrix + Playwright cache', function () {
    $path = base_path('.github/workflows/ui-baseline.yml');
    expect(file_exists($path))->toBeTrue();
    $yaml = file_get_contents($path);
    expect($yaml)->toContain('ui-baseline')
        ->toContain('--shard=')
        ->toContain('shard:')
        ->toContain('ms-playwright')
        ->toContain('pull_request');
});

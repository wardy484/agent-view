<?php

it('REQ-M11-016: browser tests workflow exists with sharded matrix + Playwright cache', function () {
    $path = base_path('.github/workflows/ui-baseline.yml');
    expect(file_exists($path))->toBeTrue();
    $yaml = file_get_contents($path);
    expect($yaml)->toContain('ui-baseline')
        ->toContain('--shard=')
        ->toContain('shard:')
        ->toContain('ms-playwright')
        ->toContain('actions/upload-artifact@v4')
        ->toContain('Tests/Browser/Screenshots')
        ->toContain('pull_request');
});

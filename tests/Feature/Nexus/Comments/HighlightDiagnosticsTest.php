<?php

declare(strict_types=1);

/**
 * REQ-M6-039: opt-in diagnostic logging for the persistent highlight
 * overlay. Gated on a `?debug-highlights=1` URL query param so we don't
 * spam production consoles. Temporary — will be reverted once we've
 * identified why first-paint highlight application keeps failing in
 * production despite three layers of triggers (REQ-M6-036/038).
 *
 * As with the other M6 frontend REQs, assertions pin the contract via
 * file-shape checks until Pest 4 browser support is wired into this
 * project.
 */
it('REQ-M6-039: overlay gates diagnostics on the ?debug-highlights URL flag', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)->toContain('?debug-highlights');
});

it('REQ-M6-039: overlay logs wrapPass internals to the browser console', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)->toContain('[highlight-overlay] wrapPass');
});

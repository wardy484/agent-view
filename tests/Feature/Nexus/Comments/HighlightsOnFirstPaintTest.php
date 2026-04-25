<?php

declare(strict_types=1);

/**
 * REQ-M6-036: persistent comment highlights apply on the snapshot's
 * first paint. The overlay observes the report container with a
 * MutationObserver so wrapping is retried whenever react-markdown
 * commits new descendants. As with the other M6 frontend REQs,
 * assertions pin the contract via file-shape checks until Pest 4
 * browser support is wired into this project.
 */
it('REQ-M6-036: spec records the first-paint highlights requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-036**')
        ->toContain('first paint')
        ->toContain('MutationObserver')
        ->toContain('childList')
        ->toContain('subtree')
        ->toContain('requestAnimationFrame');
});

it('REQ-M6-036: overlay uses MutationObserver watching childList + subtree on the report container', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('new MutationObserver')
        ->toContain('childList: true')
        ->toContain('subtree: true')
        ->toContain('observer.observe(container');
});

it('REQ-M6-036: overlay disconnects observer before mutating to avoid feedback loops, reconnects after', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('observer.disconnect()')
        ->toContain('observer.observe(container');
});

it('REQ-M6-036: overlay schedules wraps via requestAnimationFrame debounce', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('requestAnimationFrame')
        ->toContain('cancelAnimationFrame');
});

it('REQ-M6-036: overlay tears down the observer on unmount or comments-prop change', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('observer.disconnect()')
        ->toContain('[comments, containerRef, onCommentClick]');
});

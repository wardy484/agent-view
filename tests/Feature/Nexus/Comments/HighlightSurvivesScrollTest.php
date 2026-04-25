<?php

declare(strict_types=1);

/**
 * REQ-M6-034: the pending-anchor synthetic highlight (REQ-M6-027) and the
 * persistent overlay highlights (REQ-M6-028) both survive page scroll.
 *
 *  - The pill's scroll listener is restricted to `mode === 'idle'` so an
 *    in-progress composer is NOT torn down by a scroll event.
 *  - The persistent overlay's `useLayoutEffect` only depends on
 *    `[comments, containerRef, onCommentClick]` (no scroll-derived state)
 *    so scrolling never re-runs the unwrap-then-walk pass.
 */
it('REQ-M6-034: pill restricts the close-on-scroll listener to mode === idle', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');

    $source = (string) file_get_contents($path);

    // The scroll-onClose handler must be gated on mode === 'idle'. The
    // earlier `composing` boolean (anything not 'idle' was composing)
    // alone was permissive — when re-rendered with a stale `composing=false`
    // tick the listener could still fire on scroll. Pinning the gate to
    // `mode !== 'idle'` (and including `mode` in the deps array) is the
    // load-bearing detail.
    expect($source)
        ->toContain("if (!selection || mode !== 'idle')")
        // The deps array includes `mode` so the effect re-attaches when
        // the composer enters/leaves a composing state.
        ->toContain('}, [selection, mode, onClose]);');
});

it('REQ-M6-034: comment-highlight-overlay useLayoutEffect deps do not include scroll-derived state', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');

    $source = (string) file_get_contents($path);

    // The dependency array of the overlay's `useLayoutEffect` must be
    // exactly `[comments, containerRef, onCommentClick]`. Any addition
    // of a scroll-derived dependency (e.g. a scrollY ref-state) would
    // cause the effect to re-run on every scroll, unwrapping and
    // re-wrapping every persistent highlight — which is the bug class
    // REQ-M6-034 forbids.
    expect($source)
        ->toContain('}, [comments, containerRef, onCommentClick]);')
        // The effect text never reads `window.scrollY` or attaches a
        // scroll listener, so highlights are static once mounted.
        ->not->toContain("window.addEventListener('scroll'");
});

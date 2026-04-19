<?php

declare(strict_types=1);

it('REQ-M3-002: slide deck view binds keyboard navigation for ArrowLeft, ArrowRight and Space', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/slide-deck-view.tsx'));

    // Component must bind a keydown listener on window so keys work regardless
    // of focus and advance/retreat slides in response to arrow keys and Space.
    expect($source)
        ->toContain("addEventListener('keydown'")
        ->toContain("removeEventListener('keydown'")
        ->toContain('ArrowLeft')
        ->toContain('ArrowRight')
        // Space key — either ' ' or code 'Space' is acceptable.
        ->toMatch("/(event\\.key === ' '|event\\.code === 'Space')/")
        ->toContain('preventDefault');
});

it('REQ-M3-002: snapshot page dispatches to SlideDeckView for slide_deck view_type', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($source)
        ->toContain("view_type === 'slide_deck'")
        ->toContain('SlideDeckView');
});

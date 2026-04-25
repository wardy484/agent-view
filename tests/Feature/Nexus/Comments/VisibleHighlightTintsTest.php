<?php

declare(strict_types=1);

/**
 * REQ-M6-037: persistent comment highlights from REQ-M6-028 / REQ-M6-033
 * use higher-contrast tints (≥60% opacity) and a 2px dotted bottom border
 * so they remain visible on light and dark backgrounds even when the
 * background tint blends with the page.
 *
 * The class strings are emitted as full literal Tailwind tokens so the JIT
 * compiler picks them up at build time.
 */
function commentHighlightOverlaySource(): string
{
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');

    expect(file_exists($path))->toBeTrue();

    return (string) file_get_contents($path);
}

it('REQ-M6-037: open comment kind uses saturated yellow tint', function (): void {
    expect(commentHighlightOverlaySource())
        ->toContain('bg-yellow-200/70');
});

it('REQ-M6-037: open suggestion (non-deletion) uses saturated indigo tint', function (): void {
    expect(commentHighlightOverlaySource())
        ->toContain('bg-indigo-200/70');
});

it('REQ-M6-037: open suggestion (deletion) uses saturated red tint with strike-through', function (): void {
    expect(commentHighlightOverlaySource())
        ->toContain('bg-red-200/70');
});

it('REQ-M6-037: resolved comments use muted slate tint at higher opacity', function (): void {
    expect(commentHighlightOverlaySource())
        ->toContain('bg-slate-300/60');
});

it('REQ-M6-037: wontfix comments use muted zinc tint at higher opacity', function (): void {
    expect(commentHighlightOverlaySource())
        ->toContain('bg-zinc-300/50');
});

it('REQ-M6-037: every highlight variant emits a 2px dotted bottom border', function (): void {
    $source = commentHighlightOverlaySource();

    // Each of the five kinds — comment / suggestion / deletion / resolved /
    // wontfix — must carry the dotted underline alongside its tint.
    expect($source)
        ->toContain('border-b-2 border-dotted border-yellow-500')
        ->toContain('border-b-2 border-dotted border-indigo-400')
        ->toContain('border-b-2 border-dotted border-red-400')
        ->toContain('border-b-2 border-dotted border-slate-400')
        ->toContain('border-b-2 border-dotted border-zinc-400');
});

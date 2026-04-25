<?php

declare(strict_types=1);

/**
 * REQ-M6-022: always-visible mobile toolbar with click-time selection capture.
 *
 * Below `lg`, the comment selection toolbar from REQ-M6-021 is permanently
 * sticky at the viewport bottom — it never slides off-screen. When no
 * selection is active it renders in a muted "Highlight text to comment"
 * empty state with action buttons disabled. When the user taps Comment or
 * Suggest, the handler reads `window.getSelection()` directly via a shared
 * `captureSelection` helper, eliminating the selection-event timing race
 * that suppressed the toolbar on Android Chrome / iOS Safari.
 *
 * Like the rest of M6, frontend assertions pin the contract via file-shape
 * checks until Pest 4 browser support is wired into this project.
 */
it('REQ-M6-022: spec document records the always-visible toolbar requirement', function (): void {
    $spec = file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-022**')
        ->toContain('always visible')
        ->toContain('Highlight text to comment')
        ->toContain('captureSelection')
        ->toContain('window.getSelection()');
});

it('REQ-M6-022: toolbar source no longer slides off-screen via translate-y-full', function (): void {
    $source = (string) file_get_contents(
        resource_path('js/components/nexus/comment-selection-toolbar.tsx'),
    );

    // The slide-out class must be gone — the container always renders at
    // translate-y-0 below `lg`.
    expect($source)
        ->not->toContain('translate-y-full')
        ->toContain('translate-y-0');
});

it('REQ-M6-022: toolbar source renders the muted empty state copy when selection is null', function (): void {
    $source = (string) file_get_contents(
        resource_path('js/components/nexus/comment-selection-toolbar.tsx'),
    );

    expect($source)
        ->toContain('Highlight text to comment')
        // Empty-state container is testable + accessible.
        ->toContain('data-testid="comment-selection-toolbar-empty"')
        // Buttons are still rendered but disabled when there is no selection.
        ->toContain('aria-disabled');
});

it('REQ-M6-022: toolbar source captures the live selection at click time via captureSelection', function (): void {
    $source = (string) file_get_contents(
        resource_path('js/components/nexus/comment-selection-toolbar.tsx'),
    );

    expect($source)
        ->toContain('captureSelection')
        ->toContain("from '@/lib/selection-helpers'")
        // Click handlers must call captureSelection rather than passing the
        // stale React-state SelectionInfo blindly.
        ->toContain('captureSelection(');
});

it('REQ-M6-022: selection-helpers module exposes findBlockAncestor / offsetWithinBlock / captureSelection', function (): void {
    $path = resource_path('js/lib/selection-helpers.ts');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function findBlockAncestor')
        ->toContain('export function offsetWithinBlock')
        ->toContain('export function captureSelection')
        ->toContain('window.getSelection()');
});

it('REQ-M6-022: useMarkdownSelection now imports the shared helpers', function (): void {
    $hook = (string) file_get_contents(
        resource_path('js/hooks/use-markdown-selection.ts'),
    );

    expect($hook)
        ->toContain("from '@/lib/selection-helpers'")
        ->toContain('findBlockAncestor')
        ->toContain('offsetWithinBlock');
});

it('REQ-M6-022: report-view applies pb-20 lg:pb-0 to leave room for the always-visible toolbar', function (): void {
    $report = (string) file_get_contents(
        resource_path('js/components/nexus/report-view.tsx'),
    );

    expect($report)->toContain('pb-20 lg:pb-0');
});

<?php

declare(strict_types=1);

/**
 * REQ-M6-013: Selecting text within a markdown block surfaces a floating
 * action menu with `Comment`, `Suggest edit`, and `Copy`. Selection that
 * crosses block boundaries disables `Comment` and `Suggest edit`.
 *
 * REQ-M6-013 is UI-only; the project has no JS/browser test runner wired
 * up, so we follow the same pattern as `ReportDispatcherFrontendTest` and
 * assert the source files declare the contract the spec requires. When
 * Pest 4 browser tests come online, these assertions can be promoted into
 * `visit()`-based interaction tests without changing the implementation.
 */
it('REQ-M6-013: comment-selection-menu component file ships with the three actions and cross-block disable', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-menu.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function CommentSelectionMenu')
        // Three labelled actions, in order.
        ->toContain('label="Comment"')
        ->toContain('label="Suggest edit"')
        ->toContain('label="Copy"')
        // Lucide icons.
        ->toContain('MessageSquare')
        ->toContain('PenLine')
        ->toContain('Copy')
        // Cross-block selection disables comment / suggest.
        ->toContain('crossesBlocks')
        ->toContain('Selection must stay within one block.')
        // Floating positioning above the selection.
        ->toContain('rect.top')
        ->toContain('MENU_HEIGHT')
        // Escape closes the menu.
        ->toContain("event.key === 'Escape'")
        // Copy uses the existing clipboard hook (which delegates to
        // navigator.clipboard.writeText).
        ->toContain('useClipboard')
        // Test hooks for the three actions.
        ->toContain('comment-selection-menu-comment')
        ->toContain('comment-selection-menu-suggest')
        ->toContain('comment-selection-menu-copy');
});

it('REQ-M6-013: useMarkdownSelection hook captures block_id, quote, prefix, suffix, and rect', function (): void {
    $hookPath = resource_path('js/hooks/use-markdown-selection.ts');
    $helpersPath = resource_path('js/lib/selection-helpers.ts');

    expect(file_exists($hookPath))->toBeTrue();
    expect(file_exists($helpersPath))->toBeTrue();

    $hook = (string) file_get_contents($hookPath);
    // REQ-M6-022 split the helpers out of the hook into a shared module
    // (`selection-helpers.ts`); the SelectionInfo shape and DOM walk now
    // live there. The hook re-exports the type for back-compat.
    $helpers = (string) file_get_contents($helpersPath);

    expect($hook)
        ->toContain('export function useMarkdownSelection')
        // Listens to the standard selection events.
        ->toContain('selectionchange')
        ->toContain('mouseup')
        ->toContain('keyup');

    expect($helpers)
        ->toContain('export type SelectionInfo')
        // Surfaces the SelectionInfo shape required by REQ-M6-013.
        ->toContain('blockId: string | null')
        ->toContain('quote: string')
        ->toContain('prefix: string')
        ->toContain('suffix: string')
        ->toContain('startHint: number')
        ->toContain('endHint: number')
        ->toContain('rect: DOMRect')
        // Anchor-block lookup walks for the data attribute report-view sets.
        ->toContain('data-comment-block-id')
        // Cross-block selections nullify blockId so the menu can disable.
        ->toContain('sameBlock ? startBlock.getAttribute(BLOCK_ATTR) : null');
});

it('REQ-M6-013: report-view tags markdown blocks with data-comment-block-id and mounts the floating menu', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('CommentSelectionMenu')
        ->toContain('useMarkdownSelection')
        // Each markdown block carries its stable id so the hook can pin a
        // selection to a single block (REQ-M6-001 / REQ-M6-003).
        ->toContain('data-comment-block-id={id}')
        // Container ref is what the hook listens against.
        ->toContain('useRef')
        ->toContain('containerRef')
        // Comment / Suggest callbacks are wired by REQ-M6-014's openComposer
        // bridge — the floating menu still surfaces them via SelectionInfo.
        ->toContain('openComposer')
        // Escape / dismiss path clears the browser selection.
        ->toContain('clearSelection');
});

it('REQ-M6-013: Copy action uses navigator.clipboard via the shared clipboard hook', function (): void {
    $menuPath = resource_path('js/components/nexus/comment-selection-menu.tsx');
    $hookPath = resource_path('js/hooks/use-clipboard.ts');

    expect(file_exists($menuPath))->toBeTrue();
    expect(file_exists($hookPath))->toBeTrue();

    $menu = (string) file_get_contents($menuPath);
    $clipboard = (string) file_get_contents($hookPath);

    // The shared hook is the only allowed path to `navigator.clipboard` —
    // it handles unsupported-environment fallbacks and toast feedback. The
    // menu must consume it rather than reach for `navigator.clipboard`
    // directly.
    expect($clipboard)->toContain('navigator.clipboard.writeText');
    expect($menu)
        ->toContain('useClipboard')
        ->not->toContain('navigator.clipboard');
});

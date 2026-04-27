<?php

declare(strict_types=1);

/**
 * REQ-M6-013: Selecting text within a markdown block surfaces a floating
 * action menu with `Comment`, `Suggest edit`, and `Copy`. Selection that
 * crosses block boundaries keeps Comment available and disables mutating
 * suggestions / deletion.
 *
 * REQ-M6-013 is UI-only; the project has no JS/browser test runner wired
 * up, so we follow the same pattern as `ReportDispatcherFrontendTest` and
 * assert the source files declare the contract the spec requires. When
 * Pest 4 browser tests come online, these assertions can be promoted into
 * `visit()`-based interaction tests without changing the implementation.
 *
 * REQ-M6-023 renamed the menu component to `comment-selection-pill.tsx`
 * and made it the single renderer (above on lg+, below on smaller
 * screens). The contract REQ-M6-013 originally pinned still holds — three
 * actions, cross-block disable, Escape-to-close — it just lives in the
 * pill source now.
 */
it('REQ-M6-013: comment selection pill component file ships with the three actions and cross-block disable', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function CommentSelectionPill')
        // Three labelled actions, in order.
        ->toContain('label="Comment"')
        ->toContain('label="Suggest edit"')
        ->toContain('label="Copy"')
        // Lucide icons.
        ->toContain('MessageSquare')
        ->toContain('PenLine')
        ->toContain('Copy')
        // Cross-block selection disables mutating actions but not comments.
        ->toContain('crossesBlocks')
        ->toContain('Suggestions and deletion must stay within one block.')
        ->toContain('disabled={readOnly}')
        // Floating positioning derives from the selection rect.
        ->toContain('rect.top')
        // Escape closes the pill.
        ->toContain("event.key === 'Escape'")
        // Copy uses the existing clipboard hook (which delegates to
        // navigator.clipboard.writeText).
        ->toContain('useClipboard')
        // Test hooks for the three actions.
        ->toContain('comment-selection-pill-comment')
        ->toContain('comment-selection-pill-suggest')
        ->toContain('comment-selection-pill-copy');
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
        ->toContain('crossesBlocks: boolean')
        ->toContain('quote: string')
        ->toContain('prefix: string')
        ->toContain('suffix: string')
        ->toContain('startHint: number')
        ->toContain('endHint: number')
        ->toContain('rect: DOMRect')
        // Anchor-block lookup walks for the data attribute report-view sets.
        ->toContain('data-comment-block-id')
        // Cross-block selections keep the start block as the durable anchor.
        ->toContain('blockId = startBlock.getAttribute(BLOCK_ATTR)')
        ->toContain('crossesBlocks: !sameBlock');
});

it('REQ-M6-013: report-view tags markdown blocks with data-comment-block-id and mounts the floating menu', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        // REQ-M6-023: the floating menu is now the floating pill.
        ->toContain('CommentSelectionPill')
        ->toContain('useMarkdownSelection')
        // Each markdown block carries its stable id so the hook can pin a
        // selection to a single block (REQ-M6-001 / REQ-M6-003).
        ->toContain('data-comment-block-id={id}')
        // Container ref is what the hook listens against.
        ->toContain('useRef')
        ->toContain('containerRef')
        // REQ-M6-030: openComposer plumbing was removed — the floating pill
        // hosts its inline composer (REQ-M6-027) and POSTs new comments
        // itself. The Comment / Suggest callbacks remain on the pill for
        // observability and historical-mode gating; we just don't pin the
        // legacy bridge name any more.
        ->toContain('handleComment')
        ->toContain('handleSuggest')
        // Escape / dismiss path clears the browser selection.
        ->toContain('clearSelection');
});

it('REQ-M6-013: Copy action uses navigator.clipboard via the shared clipboard hook', function (): void {
    $pillPath = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $hookPath = resource_path('js/hooks/use-clipboard.ts');

    expect(file_exists($pillPath))->toBeTrue();
    expect(file_exists($hookPath))->toBeTrue();

    $pill = (string) file_get_contents($pillPath);
    $clipboard = (string) file_get_contents($hookPath);

    // The shared hook is the only allowed path to `navigator.clipboard` —
    // it handles unsupported-environment fallbacks and toast feedback. The
    // pill must consume it rather than reach for `navigator.clipboard`
    // directly.
    expect($clipboard)->toContain('navigator.clipboard.writeText');
    expect($pill)
        ->toContain('useClipboard')
        ->not->toContain('navigator.clipboard');
});

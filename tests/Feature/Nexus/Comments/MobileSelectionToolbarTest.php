<?php

declare(strict_types=1);

/**
 * REQ-M6-021: mobile-friendly selection actions for the report view.
 *
 * Below Tailwind's `lg` breakpoint, REQ-M6-013's floating selection menu
 * is replaced by a sticky bottom toolbar that surfaces whenever a
 * non-collapsed selection sits inside a markdown block. The toolbar carries
 * the same three actions (Comment, Suggest edit, Copy) and the same
 * cross-block disable rule. Touch selections are also wired up by extending
 * `useMarkdownSelection` to listen for `selectionchange` and `pointerup`
 * events (the existing `mouseup`/`keyup` pair never fired reliably on iOS
 * Safari).
 *
 * Like its M6 siblings, this REQ has no JS browser test runner yet; we
 * pin the contract via file-shape assertions and promote them to `visit()`
 * tests once Pest 4 browser support lands.
 */
it('REQ-M6-021: spec document records the mobile selection toolbar requirement', function (): void {
    $spec = file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-021**')
        ->toContain('comment-selection-toolbar.tsx')
        ->toContain('selectionchange')
        ->toContain('pointerup')
        ->toContain('Comment, Suggest edit, Copy')
        ->toContain('lg');
});

it('REQ-M6-021: useMarkdownSelection hook subscribes to selectionchange and pointerup', function (): void {
    $hook = file_get_contents(
        resource_path('js/hooks/use-markdown-selection.ts'),
    );

    expect($hook)
        ->toContain("addEventListener('selectionchange'")
        ->toContain("removeEventListener('selectionchange'")
        ->toContain("addEventListener('pointerup'")
        ->toContain("removeEventListener('pointerup'")
        // Desktop listeners must remain.
        ->toContain("addEventListener('mouseup'")
        ->toContain("addEventListener('keyup'");
});

it('REQ-M6-021: useMarkdownSelection scopes selectionchange to anchor inside the container ref', function (): void {
    $hook = file_get_contents(
        resource_path('js/hooks/use-markdown-selection.ts'),
    );

    // The selectionchange handler must consult the container ref + check
    // anchorNode containment so unrelated selections elsewhere on the page
    // do not clobber the hook's state. Debounce keeps iOS's many-per-touch
    // events from thrashing React.
    expect($hook)
        ->toContain('anchorNode')
        ->toContain('container.contains(anchorNode)')
        ->toContain('requestAnimationFrame');
});

it('REQ-M6-021: comment-selection-toolbar renders three actions with mobile-sized hit targets', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-toolbar.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function CommentSelectionToolbar')
        // Three labelled actions, in order: Comment, Suggest edit, Copy.
        ->toContain('label="Comment"')
        ->toContain('label="Suggest edit"')
        ->toContain('label="Copy"')
        // Lucide icons mirror the floating menu.
        ->toContain('MessageSquare')
        ->toContain('PenLine')
        // Sticky bottom positioning + safe-area inset for iOS notch.
        ->toContain('fixed inset-x-0 bottom-0')
        ->toContain('env(safe-area-inset-bottom)')
        // Slide-up / slide-down animation.
        ->toContain('translate-y-0')
        // REQ-M6-022 removed translate-y-full (always-visible toolbar)
        // ->toContain('translate-y-full')
        ->toContain('transition-transform')
        // Mobile-sized hit targets (h-12, text-base).
        ->toContain('h-12')
        ->toContain('text-base')
        // Stable test ids for promotion to visit() tests later.
        ->toContain('data-testid="comment-selection-toolbar"')
        ->toContain('comment-selection-toolbar-comment')
        ->toContain('comment-selection-toolbar-suggest')
        ->toContain('comment-selection-toolbar-copy');
});

it('REQ-M6-021: comment-selection-toolbar disables Comment and Suggest when selection crosses blocks', function (): void {
    $source = (string) file_get_contents(
        resource_path('js/components/nexus/comment-selection-toolbar.tsx'),
    );

    expect($source)
        ->toContain('crossesBlocks')
        ->toContain('disableWriteActions')
        ->toContain('Selection must stay within one block.')
        // Copy always enabled when there's a selection (only gated on null).
        ->toContain('disableCopy');
});

it('REQ-M6-021: report-view shows toolbar below lg and floating menu above lg', function (): void {
    $report = file_get_contents(
        resource_path('js/components/nexus/report-view.tsx'),
    );

    expect($report)
        ->toContain("from '@/components/nexus/comment-selection-toolbar'")
        ->toContain('<CommentSelectionToolbar')
        // Desktop wrapper: hidden lg:block — only renders the floating menu
        // on >= lg viewports.
        ->toContain('hidden lg:block')
        // Mobile wrapper: lg:hidden — only renders the sticky toolbar on
        // < lg viewports.
        ->toContain('lg:hidden');
});

it('REQ-M6-021: toolbar listens for Escape to close', function (): void {
    $source = (string) file_get_contents(
        resource_path('js/components/nexus/comment-selection-toolbar.tsx'),
    );

    expect($source)
        ->toContain("event.key === 'Escape'")
        ->toContain('onClose')
        ->toContain("addEventListener('keydown'")
        ->toContain("removeEventListener('keydown'");
});

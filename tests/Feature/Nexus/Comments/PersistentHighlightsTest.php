<?php

declare(strict_types=1);

/**
 * REQ-M6-028: persistent inline highlights for comments whose anchor
 * resolves on the current revision. As with the other M6 frontend REQs,
 * assertions pin the contract via file-shape checks until Pest 4 browser
 * support is wired into this project.
 */
it('REQ-M6-028: spec records the persistent highlights requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-028**')
        ->toContain('comment-highlight-overlay.tsx')
        ->toContain('resolved_in_current_version === true')
        // Status-keyed styling.
        ->toContain('bg-yellow-200/40')
        ->toContain('line-through')
        ->toContain('wontfix')
        ->toContain('idempotent');
});

it('REQ-M6-028: comment-highlight-overlay component file exists', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function CommentHighlightOverlay')
        ->toContain('useLayoutEffect')
        // Mounts wrapping marks via DOM mutation, not via React tree.
        ->toContain("document.createElement('mark')");
});

it('REQ-M6-028: overlay walks comments and wraps anchors with status-tagged marks', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('data-comment-overlay')
        ->toContain('data-comment-id')
        ->toContain('data-status')
        // Resolves the anchor via prefix/suffix disambiguation in the host block.
        ->toContain('data-comment-block-id')
        ->toContain('anchor.quote')
        ->toContain('anchor.prefix')
        ->toContain('anchor.suffix')
        // Open / resolved / wontfix all carry styling; stale renders nothing.
        ->toContain('open:')
        ->toContain('resolved:')
        ->toContain('wontfix:')
        // Stale anchors must not produce a mark.
        ->toContain("c.status !== 'stale'")
        ->toContain('resolved_in_current_version === true');
});

it('REQ-M6-028: open status uses yellow tint, resolved uses slate + line-through, wontfix uses grey + strike', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('bg-yellow-200/40 dark:bg-yellow-900/40')
        ->toContain('bg-slate-300/40 dark:bg-slate-700/40 line-through')
        ->toContain('bg-zinc-300/30 dark:bg-zinc-700/30 line-through opacity-60');
});

it('REQ-M6-028: tooltip surfaces author display name + first body line', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('comment.author.display_name')
        ->toContain('comment.body.split')
        // Tooltip body is exposed via title + aria-label + data-tooltip.
        ->toContain('mark.title = tooltipLabel')
        ->toContain("setAttribute('aria-label', tooltipLabel)");
});

it('REQ-M6-028: clicking a highlight invokes onCommentClick(id)', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('onCommentClick')
        ->toContain("addEventListener('click'")
        ->toContain('onCommentClick(comment.id)');
});

it('REQ-M6-028: overlay is idempotent — re-runs unwrap previous marks before walking', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('unwrapOverlayMarks')
        // useLayoutEffect re-runs on comments-prop change.
        ->toContain('[comments, containerRef, onCommentClick]')
        // The overlay attribute drives the unwrap query.
        ->toContain('data-comment-overlay="true"');
});

it('REQ-M6-028: report-view mounts CommentHighlightOverlay against the report container', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('CommentHighlightOverlay')
        ->toContain('comments={comments}')
        ->toContain('containerRef={containerRef}')
        ->toContain('onCommentClick=');
});

it('REQ-M6-028: clicking a highlight switches sidebar to Comments tab and scrolls to the row', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('snapshot-sidebar-tab-comments')
        ->toContain('data-comment-id="')
        ->toContain('scrollIntoView');
});

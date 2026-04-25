<?php

declare(strict_types=1);

/**
 * REQ-M6-027: tapping the pill's Comment or Suggest edit button expands
 * it inline into a composer, wraps the selected text in a synthetic
 * `<mark data-pending-anchor>`, and POSTs to the existing
 * /snapshots/{s}/comments endpoint on Submit.
 *
 * As with the other M6 frontend REQs, assertions pin the contract via
 * file-shape checks until Pest 4 browser support is wired into this
 * project.
 */
it('REQ-M6-027: spec records the inline composer requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-027**')
        ->toContain('inline into a composer')
        ->toContain('mark data-pending-anchor')
        ->toContain('synthesizeHighlight')
        ->toContain('removeSyntheticHighlight');
});

it('REQ-M6-027: pill source declares three modes (idle, composing-comment, composing-suggestion)', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain("'idle'")
        ->toContain("'composing-comment'")
        ->toContain("'composing-suggestion'")
        ->toContain('type Mode');
});

it('REQ-M6-027: pill captures selection then wraps with synthetic mark on Comment tap', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('captureSelection')
        ->toContain('synthesizeHighlight')
        // Live OS selection is cleared after wrapping so the OS bubble vanishes.
        ->toContain('removeAllRanges()')
        // The captured range is cloned before we mutate the DOM.
        ->toContain('cloneRange');
});

it('REQ-M6-027: pill submit POSTs to /snapshots/{id}/comments and reloads only comments prop', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('router.post(')
        ->toContain('/snapshots/${snapshotId}/comments')
        ->toContain("only: ['comments']")
        ->toContain('block_id')
        ->toContain('anchor_quote')
        ->toContain('anchor_prefix')
        ->toContain('anchor_suffix')
        ->toContain('anchor_start_hint')
        ->toContain('anchor_end_hint')
        ->toContain('proposed_text');
});

it('REQ-M6-027: cancel removes the synthetic mark and resets to idle', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('removeSyntheticHighlight')
        ->toContain('comment-selection-pill-cancel')
        // resetComposer wires together the unwrap + state reset.
        ->toContain('resetComposer');
});

it('REQ-M6-027: composer body has textarea + Submit + Cancel and Suggest gets a proposed-text field', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('comment-selection-pill-body')
        ->toContain('comment-selection-pill-proposed')
        ->toContain('comment-selection-pill-submit')
        ->toContain('comment-selection-pill-cancel')
        // Composer container has its own data-testid for browser tests.
        ->toContain('comment-selection-pill-composer')
        // data-mode attribute exposes the current mode for assertions.
        ->toContain('data-mode');
});

it('REQ-M6-027: selection-helpers exposes synthesizeHighlight and removeSyntheticHighlight', function (): void {
    $path = resource_path('js/lib/selection-helpers.ts');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function synthesizeHighlight')
        ->toContain('export function removeSyntheticHighlight')
        ->toContain('data-pending-anchor');
});

it('REQ-M6-027: composer keeps its anchor position relative to the selection (clamped inside viewport)', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Position math reads from the synthetic mark's rect while composing.
        ->toContain('pendingAnchorRect')
        ->toContain('getBoundingClientRect')
        // Cannot exceed the viewport's smaller dimension.
        ->toContain('Math.min(viewportWidth, viewportHeight)');
});

it('REQ-M6-027: report-view passes snapshotId into the pill', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('snapshotId={snapshotId}');
});

it('REQ-M6-027: store endpoint accepts the inline composer payload', function (): void {
    // Existing controller already accepts the shape; this regression-locks
    // the validation rules the inline composer relies on.
    $path = app_path('Http/Controllers/Snapshots/CommentController.php');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain("'block_id' => ['required', 'string', 'uuid'],")
        ->toContain("'kind' => ['required', 'in:comment,suggestion'],")
        ->toContain("'anchor_quote'")
        ->toContain("'anchor_start_hint'")
        ->toContain("'anchor_end_hint'");
});

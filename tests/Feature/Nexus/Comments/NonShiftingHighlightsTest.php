<?php

declare(strict_types=1);

/**
 * REQ-M6-035: highlight wrapping must not shift document layout.
 *
 * Both the persistent overlay (REQ-M6-028) and the pending-anchor synthetic
 * mark (REQ-M6-027) previously called `range.surroundContents()` (with
 * `extractContents()` as a fallback). Both behaviours produced visible
 * layout shifts when the selected range spanned multiple text nodes —
 * paragraphs broke, words split mid-letter across `<code>` / `<strong>`
 * boundaries, content reflowed.
 *
 * The fix is a shared TreeWalker-based helper in `selection-helpers.ts`
 * that emits ONE inline `<mark>` per intersecting text-node sub-range.
 * All marks for a single logical highlight share the same data attribute
 * (`data-comment-id` for persistent, `data-pending-anchor` for pending).
 *
 * As elsewhere in M6, the assertions pin the contract via file-shape
 * checks until Pest 4 browser tests are wired in for this project.
 */
it('REQ-M6-035: spec records the non-shifting highlight requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-035**')
        ->toContain('per text node sub-range')
        ->toContain('wrapRangeWithMarks')
        ->toContain('unwrapMarks')
        ->toContain('parent.normalize()');
});

it('REQ-M6-035: selection-helpers exports the shared wrapping helpers', function (): void {
    $path = resource_path('js/lib/selection-helpers.ts');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function wrapRangeWithMarks')
        ->toContain('export function unwrapMarks')
        // Range walking strategy: TreeWalker over text nodes, intersected
        // with the live Range — never surroundContents/extractContents.
        ->toContain('createTreeWalker')
        ->toContain('SHOW_TEXT')
        ->toContain('intersectsNode')
        // splitText is the safe per-text-node split mechanism.
        ->toContain('splitText');
});

it('REQ-M6-035: selection-helpers no longer uses surroundContents or extractContents for wrapping', function (): void {
    $path = resource_path('js/lib/selection-helpers.ts');
    $source = (string) file_get_contents($path);

    // surroundContents / extractContents are the APIs that shift the DOM
    // when a range crosses element boundaries. The whole point of the REQ
    // is that NEITHER appears in any wrapping path.
    expect($source)
        ->not->toContain('surroundContents')
        ->not->toContain('extractContents');
});

it('REQ-M6-035: synthesizeHighlight returns an array of inline marks via wrapRangeWithMarks', function (): void {
    $path = resource_path('js/lib/selection-helpers.ts');
    $source = (string) file_get_contents($path);

    expect($source)
        // synthesizeHighlight delegates to the shared helper rather than
        // calling surroundContents / extractContents inline.
        ->toContain('export function synthesizeHighlight')
        ->toContain('wrapRangeWithMarks(range, ()')
        // Return shape is HTMLElement[] — one mark per text-node sub-range.
        ->toContain('HTMLElement[]')
        // Each mark carries the pending-anchor sentinel.
        ->toContain("setAttribute(PENDING_ANCHOR_ATTR, 'true')");
});

it('REQ-M6-035: removeSyntheticHighlight unwraps every pending-anchor element and normalizes', function (): void {
    $path = resource_path('js/lib/selection-helpers.ts');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function removeSyntheticHighlight')
        // Delegates to the shared unwrap helper, which iterates every
        // matching mark — not just one.
        ->toContain('unwrapMarks(document, `mark[${PENDING_ANCHOR_ATTR}="true"]`)')
        // unwrapMarks itself coalesces siblings via parent.normalize().
        ->toContain('parent.normalize()');
});

it('REQ-M6-035: comment-highlight-overlay uses wrapRangeWithMarks instead of surroundContents', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Imports the shared algorithm.
        ->toContain("from '@/lib/selection-helpers'")
        ->toContain('wrapRangeWithMarks')
        ->toContain('unwrapMarks')
        // No DOM-shifting APIs anywhere in the overlay.
        ->not->toContain('surroundContents')
        ->not->toContain('extractContents');
});

it('REQ-M6-035: overlay still tags every mark with data-comment-id and click handler', function (): void {
    // Multiple marks per comment must share the data-comment-id attribute
    // and each must wire the click listener — that's how the report view's
    // event-delegation contract continues to work uniformly across the
    // sibling marks produced for a paragraph-spanning anchor.
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain("setAttribute('data-comment-id', String(comment.id))")
        ->toContain("addEventListener('click'")
        ->toContain('onCommentClick(comment.id)')
        // Each mark also carries the kind / status / overlay sentinel.
        ->toContain("setAttribute(OVERLAY_ATTR, 'true')")
        ->toContain("setAttribute('data-status', comment.status)")
        ->toContain("setAttribute('data-kind', comment.kind)");
});

it('REQ-M6-035: pill consumes synthesizeHighlight as an array of marks', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // The pill calls the helper and treats the result as a list.
        ->toContain('const marks = synthesizeHighlight(range)')
        ->toContain('marks.length === 0')
        // It still anchors the composer rect to the first mark.
        ->toContain('marks[0]')
        ->toContain('getBoundingClientRect');
});

it('REQ-M6-035: wrapRangeWithMarks emits one mark per text-node sub-range (DOM shape integration)', function (): void {
    // This is the only DOM-level invariant we can assert from PHP without a
    // headless browser: the source code shape that GUARANTEES the per-text-
    // node behaviour. The body of wrapRangeWithMarks must walk text nodes,
    // call splitText to isolate the in-range portion, then insertBefore +
    // appendChild — never surroundContents and never extractContents.
    $path = resource_path('js/lib/selection-helpers.ts');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('const textNodes: Text[] = [];')
        ->toContain('walker.nextNode()')
        // Per text-node range bounds.
        ->toContain('textNode === range.startContainer ? range.startOffset : 0')
        ->toContain('textNode === range.endContainer')
        // splitText carves out the in-range portion.
        ->toContain('target.splitText(start)')
        ->toContain('target.splitText(end - start)')
        // Insert + append: pure inline wrap, no block element introduced.
        ->toContain('parent.insertBefore(mark, target)')
        ->toContain('mark.appendChild(target)');
});

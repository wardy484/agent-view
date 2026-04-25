/**
 * REQ-M6-022: shared selection helpers.
 *
 * `findBlockAncestor` and `offsetWithinBlock` were originally inlined in
 * `use-markdown-selection.ts` (REQ-M6-013 / REQ-M6-021). This module is a
 * pure refactor: behaviour is unchanged. They are extracted so that
 * `comment-selection-pill.tsx` can call `captureSelection` at click
 * time without depending on the hook's React state — which is the fix for
 * the iOS/Android touch race described in REQ-M6-022.
 *
 * `captureSelection` reads `window.getSelection()` synchronously and
 * returns a fresh `SelectionInfo`. The OS selection bubble has finalised
 * the selection by the time the user taps an always-visible toolbar
 * button, so the DOM read is reliable. No event-timing race.
 */

export type SelectionInfo = {
    blockId: string | null;
    quote: string;
    prefix: string;
    suffix: string;
    startHint: number;
    endHint: number;
    rect: DOMRect;
};

const PREFIX_SUFFIX_LEN = 32;
const BLOCK_ATTR = 'data-comment-block-id';

export function findBlockAncestor(node: Node | null, container: HTMLElement): HTMLElement | null {
    let current: Node | null = node;

    while (current && current !== container) {
        if (current.nodeType === Node.ELEMENT_NODE) {
            const el = current as HTMLElement;

            if (el.hasAttribute(BLOCK_ATTR)) {
                return el;
            }
        }

        current = current.parentNode;
    }

    return null;
}

export function offsetWithinBlock(block: HTMLElement, node: Node, nodeOffset: number): number {
    // Walk text nodes in document order, summing their lengths until we hit
    // the target node. Maps a (node, offset) tuple to a flat character
    // offset against the block's visible text. See use-markdown-selection.ts
    // for the original caveat about source-vs-rendered drift.
    if (!block.contains(node)) {
        return -1;
    }

    const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
    let offset = 0;
    let textNode = walker.nextNode();

    while (textNode) {
        if (textNode === node) {
            return offset + nodeOffset;
        }

        offset += textNode.textContent?.length ?? 0;
        textNode = walker.nextNode();
    }

    if (node.nodeType !== Node.TEXT_NODE && block.contains(node)) {
        return offset;
    }

    return -1;
}

/**
 * REQ-M6-022: read the live `window.getSelection()` and translate it into
 * a `SelectionInfo` against `container`. Returns `null` when the selection
 * is collapsed, empty, outside the container, or missing entirely.
 *
 * This is called both from `useMarkdownSelection` (reactive feedback) and
 * from the always-visible toolbar's click handlers (authoritative read at
 * tap time).
 */
export function captureSelection(container: HTMLElement | null): SelectionInfo | null {
    if (!container) {
        return null;
    }

    const sel = window.getSelection();

    if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
        return null;
    }

    const range = sel.getRangeAt(0);

    if (!container.contains(range.startContainer) || !container.contains(range.endContainer)) {
        return null;
    }

    const quote = sel.toString();

    if (quote.length === 0) {
        return null;
    }

    const startBlock = findBlockAncestor(range.startContainer, container);
    const endBlock = findBlockAncestor(range.endContainer, container);

    if (!startBlock || !endBlock) {
        return null;
    }

    const sameBlock = startBlock === endBlock;
    const block = sameBlock ? startBlock : null;
    const blockId = sameBlock ? startBlock.getAttribute(BLOCK_ATTR) : null;

    const rect = range.getBoundingClientRect();

    const refBlock = block ?? startBlock;
    const refText = refBlock.textContent ?? '';
    const startHint = offsetWithinBlock(refBlock, range.startContainer, range.startOffset);

    const endHint = sameBlock
        ? offsetWithinBlock(refBlock, range.endContainer, range.endOffset)
        : startHint + quote.length;

    const safeStart = startHint < 0 ? 0 : startHint;
    const safeEnd = endHint < 0 ? safeStart + quote.length : endHint;

    const prefix = refText.slice(Math.max(0, safeStart - PREFIX_SUFFIX_LEN), safeStart);
    const suffix = refText.slice(safeEnd, safeEnd + PREFIX_SUFFIX_LEN);

    return {
        blockId,
        quote,
        prefix,
        suffix,
        startHint: safeStart,
        endHint: safeEnd,
        rect,
    };
}

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


/**
 * REQ-M6-027: synthetic highlight management for the inline composer.
 *
 * When the pill expands into a composer, the live OS selection is dismissed
 * (the user is about to type into a textarea) so we wrap the captured range
 * in `<mark data-pending-anchor>` element(s) to keep the selected text
 * visibly highlighted. The marks are unwrapped on Submit / Cancel / Escape.
 *
 * REQ-M6-035: highlight wrapping must not shift the document layout. The
 * implementation walks the range with a TreeWalker (`wrapRangeWithMarks`)
 * and emits ONE `<mark>` per intersecting text-node sub-range. Each wrap
 * is a pure text-node split (`splitText`) followed by a parent-equivalent
 * inline wrap (`insertBefore` + `appendChild`) — no block-level elements
 * introduced, no whitespace inserted, no `display` overrides applied. A
 * paragraph-spanning quote becomes multiple inline `<mark>` elements that
 * share a common attribute (e.g. `data-comment-id`, `data-pending-anchor`).
 */
export const PENDING_ANCHOR_ATTR = 'data-pending-anchor';

/**
 * REQ-M6-035: walk `range`, splitting each intersecting text node so that
 * its in-range portion can be wrapped by a fresh `<mark>` produced by
 * `factory`. `factory` is invoked once per emitted mark so that callers can
 * attach event listeners, attributes, and classes per-mark; ALL marks for a
 * single logical highlight share the same `data-*` attributes (the caller
 * is responsible for that — `factory` is called once per text-node split,
 * but the caller passes the same identity into each invocation).
 *
 * Returns the array of inserted marks in document order.
 *
 * Invariants:
 *   - Each `<mark>` wraps exactly one text-node sub-range.
 *   - No `<mark>` ever contains a non-text child; therefore zero block-
 *     level shift, zero whitespace insertion.
 *   - The DOM-shifting Range APIs that relocate non-text children when a
 *     range crosses element boundaries are never used inside this helper.
 */
export function wrapRangeWithMarks(
    range: Range,
    factory: () => HTMLElement,
): HTMLElement[] {
    const marks: HTMLElement[] = [];

    if (range.collapsed) {
        return marks;
    }

    const root = range.commonAncestorContainer;
    const walkRoot =
        root.nodeType === Node.TEXT_NODE ? (root.parentNode as Node | null) : root;

    if (!walkRoot) {
        return marks;
    }

    const walker = document.createTreeWalker(walkRoot, NodeFilter.SHOW_TEXT, {
        acceptNode(node: Node): number {
            try {
                return range.intersectsNode(node)
                    ? NodeFilter.FILTER_ACCEPT
                    : NodeFilter.FILTER_REJECT;
            } catch {
                return NodeFilter.FILTER_REJECT;
            }
        },
    });

    const textNodes: Text[] = [];
    let n: Node | null = walker.nextNode();

    while (n) {
        textNodes.push(n as Text);
        n = walker.nextNode();
    }

    for (const textNode of textNodes) {
        const start =
            textNode === range.startContainer ? range.startOffset : 0;
        const end =
            textNode === range.endContainer
                ? range.endOffset
                : textNode.length;

        if (start >= end) {
            continue;
        }

        // Split the text node so the in-range portion is a standalone node.
        // After two splits, `target` holds the exact substring to wrap and
        // remains a sibling under the original parent; `tail` holds the
        // remaining text after the range end (already in the DOM).
        let target: Text = textNode;

        if (start > 0) {
            target = target.splitText(start);
        }

        if (end - start < target.length) {
            target.splitText(end - start);
        }

        const parent = target.parentNode;

        if (!parent) {
            continue;
        }

        const mark = factory();
        parent.insertBefore(mark, target);
        mark.appendChild(target);
        marks.push(mark);
    }

    return marks;
}

/**
 * REQ-M6-035: unwrap every element matched by `selector` under `root`,
 * preserving the wrapped content in-place and coalescing adjacent text
 * nodes back together via `parent.normalize()`.
 */
export function unwrapMarks(
    root: ParentNode | Document,
    selector: string,
): void {
    const marks = root.querySelectorAll<HTMLElement>(selector);
    const parents = new Set<Node>();

    marks.forEach((mark) => {
        const parent = mark.parentNode;

        if (!parent) {
            return;
        }

        parents.add(parent);

        while (mark.firstChild) {
            parent.insertBefore(mark.firstChild, mark);
        }

        parent.removeChild(mark);
    });

    parents.forEach((parent) => {
        if (parent.nodeType === Node.ELEMENT_NODE || parent.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
            (parent as Element).normalize();
        }
    });
}

export function synthesizeHighlight(range: Range): HTMLElement[] {
    // REQ-M6-035: emit ONE inline <mark> per text-node sub-range, sharing
    // `data-pending-anchor`. Replaces the previous wrapping path that
    // shifted the document layout when the range crossed element
    // boundaries.
    const marks = wrapRangeWithMarks(range, () => {
        const mark = document.createElement('mark');
        mark.setAttribute(PENDING_ANCHOR_ATTR, 'true');
        mark.className = 'rounded bg-amber-200/60 px-0.5 dark:bg-amber-900/50';

        return mark;
    });

    return marks;
}

export function removeSyntheticHighlight(): void {
    // REQ-M6-035: unwrap ALL pending-anchor marks (a single logical
    // highlight may have produced many inline <mark> elements). Adjacent
    // text-node fragments are re-coalesced via normalize().
    unwrapMarks(document, `mark[${PENDING_ANCHOR_ATTR}="true"]`);
}

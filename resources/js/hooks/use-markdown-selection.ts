import { useEffect, useState } from 'react';

/**
 * REQ-M6-013: tracks the user's text selection inside a container that hosts
 * markdown blocks. Each markdown block in the report renderer is wrapped in
 * an element carrying `data-comment-block-id="<uuid>"`; this hook walks up
 * from the selection's start/end nodes to find that ancestor.
 *
 * If the selection's start and end share the same `data-comment-block-id`,
 * the resulting `SelectionInfo.blockId` is that ID. Otherwise `blockId` is
 * `null` — the menu uses this to disable the comment-side actions (anchors
 * are block-scoped per REQ-M6-003).
 *
 * Caveat: `startHint` / `endHint` are character offsets computed by walking
 * the rendered DOM's text content within the block. The block's source
 * markdown body and its rendered text are equivalent for plain prose, but
 * formatting (bold, italics, code, links) introduces drift of a few chars.
 * That is fine: the server-side `AnchorResolver` (REQ-M6-004) re-resolves
 * via the `quote` + `prefix` + `suffix` triple, treating the hints as a
 * starting point only.
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

function findBlockAncestor(node: Node | null, container: HTMLElement): HTMLElement | null {
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

function offsetWithinBlock(block: HTMLElement, node: Node, nodeOffset: number): number {
    // Walk text nodes in document order, summing their lengths until we hit
    // the target node. This maps a (node, offset) tuple in the block's
    // rendered subtree to a flat character offset against the block's
    // visible text — which is approximately the source markdown body for
    // plain prose. See file-level caveat.
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

    // If `node` is itself an element (not a text node), `nodeOffset` is the
    // index among its children — sum every text node before that point.
    if (node.nodeType !== Node.TEXT_NODE && block.contains(node)) {
        return offset;
    }

    return -1;
}

export function useMarkdownSelection(
    containerRef: React.RefObject<HTMLElement | null>,
): SelectionInfo | null {
    const [info, setInfo] = useState<SelectionInfo | null>(null);

    useEffect(() => {
        const compute = () => {
            const container = containerRef.current;

            if (!container) {
                return;
            }

            const sel = window.getSelection();

            if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
                setInfo(null);

                return;
            }

            const range = sel.getRangeAt(0);

            if (!container.contains(range.startContainer) || !container.contains(range.endContainer)) {
                setInfo(null);

                return;
            }

            const quote = sel.toString();

            if (quote.length === 0) {
                setInfo(null);

                return;
            }

            const startBlock = findBlockAncestor(range.startContainer, container);
            const endBlock = findBlockAncestor(range.endContainer, container);

            if (!startBlock || !endBlock) {
                setInfo(null);

                return;
            }

            const sameBlock = startBlock === endBlock;
            const block = sameBlock ? startBlock : null;
            const blockId = sameBlock ? startBlock.getAttribute(BLOCK_ATTR) : null;

            const rect = range.getBoundingClientRect();

            // For cross-block selections, prefix/suffix/hints are best-effort
            // and unused (the menu disables comment actions). Compute against
            // the start block in that case so we still surface SOMETHING.
            const refBlock = block ?? startBlock;
            const refText = refBlock.textContent ?? '';
            const startHint = offsetWithinBlock(refBlock, range.startContainer, range.startOffset);

            // For end hint, use start block only when same-block; otherwise
            // approximate to startHint + quote.length.
            const endHint = sameBlock
                ? offsetWithinBlock(refBlock, range.endContainer, range.endOffset)
                : startHint + quote.length;

            const safeStart = startHint < 0 ? 0 : startHint;
            const safeEnd = endHint < 0 ? safeStart + quote.length : endHint;

            const prefix = refText.slice(Math.max(0, safeStart - PREFIX_SUFFIX_LEN), safeStart);
            const suffix = refText.slice(safeEnd, safeEnd + PREFIX_SUFFIX_LEN);

            setInfo({
                blockId,
                quote,
                prefix,
                suffix,
                startHint: safeStart,
                endHint: safeEnd,
                rect,
            });
        };

        const onSelectionChange = () => {
            // selectionchange fires on every keystroke; debounce via raf so we
            // don't thrash React state on every micro-update.
            requestAnimationFrame(compute);
        };

        document.addEventListener('selectionchange', onSelectionChange);
        document.addEventListener('mouseup', compute);
        document.addEventListener('keyup', compute);

        return () => {
            document.removeEventListener('selectionchange', onSelectionChange);
            document.removeEventListener('mouseup', compute);
            document.removeEventListener('keyup', compute);
        };
    }, [containerRef]);

    return info;
}

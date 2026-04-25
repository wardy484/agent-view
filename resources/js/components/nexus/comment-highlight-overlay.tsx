import { useLayoutEffect } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * REQ-M6-028: persistent inline highlights for comments whose anchor
 * resolves on the current revision.
 *
 * Walks the report container in a `useLayoutEffect` and wraps each
 * resolved anchor (`anchor.resolved_in_current_version === true`) in a
 * `<mark data-comment-overlay="true" data-comment-id="…" data-status="…">`
 * element. Idempotent: existing overlay marks are unwrapped before a
 * fresh walk. Stale anchors render no mark.
 */

export type HighlightCommentSummary = {
    id: number;
    block_id: string;
    status: 'open' | 'resolved' | 'wontfix' | 'stale';
    body: string;
    anchor: {
        quote: string;
        prefix?: string;
        suffix?: string;
        resolved_in_current_version: boolean;
    };
    author: {
        display_name: string;
    };
};

type Props = {
    comments: HighlightCommentSummary[];
    containerRef: React.RefObject<HTMLElement | null>;
    onCommentClick: (commentId: number) => void;
};

const OVERLAY_ATTR = 'data-comment-overlay';

const STATUS_CLASSES: Record<string, string> = {
    open: 'bg-yellow-200/40 dark:bg-yellow-900/40',
    resolved: 'bg-slate-300/40 dark:bg-slate-700/40 line-through decoration-slate-500',
    wontfix: 'bg-zinc-300/30 dark:bg-zinc-700/30 line-through opacity-60',
};

export function CommentHighlightOverlay({
    comments,
    containerRef,
    onCommentClick,
}: Props) {
    useLayoutEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        // Idempotent re-run: unwrap any prior overlay marks before walking.
        unwrapOverlayMarks(container);

        const eligible = comments.filter(
            (c) =>
                c.anchor.resolved_in_current_version === true &&
                c.status !== 'stale' &&
                STATUS_CLASSES[c.status] !== undefined,
        );

        for (const comment of eligible) {
            const block = container.querySelector<HTMLElement>(
                `[data-comment-block-id="${comment.block_id}"]`,
            );

            if (!block) {
                continue;
            }

            const range = findAnchorRange(
                block,
                comment.anchor.quote,
                comment.anchor.prefix ?? '',
                comment.anchor.suffix ?? '',
            );

            if (!range) {
                continue;
            }

            const mark = document.createElement('mark');
            mark.setAttribute(OVERLAY_ATTR, 'true');
            mark.setAttribute('data-comment-id', String(comment.id));
            mark.setAttribute('data-status', comment.status);
            mark.className = `${STATUS_CLASSES[comment.status]} cursor-pointer rounded px-0.5 hover:ring-2 hover:ring-amber-400`;

            const firstLine = (comment.body.split(/\r?\n/)[0] ?? '').trim();
            const tooltipLabel = `${comment.author.display_name}: ${firstLine}`;
            mark.title = tooltipLabel;
            mark.setAttribute('aria-label', tooltipLabel);
            mark.setAttribute('data-tooltip', tooltipLabel);

            mark.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                onCommentClick(comment.id);
            });

            try {
                range.surroundContents(mark);
            } catch {
                try {
                    const contents = range.extractContents();
                    mark.appendChild(contents);
                    range.insertNode(mark);
                } catch {
                    // Give up on this anchor; leave the block as-is.
                }
            }
        }

        return () => {
            // Cleanup on unmount or before re-run.
            if (container.isConnected) {
                unwrapOverlayMarks(container);
            }
        };
    }, [comments, containerRef, onCommentClick]);

    // The component itself renders no DOM; it only mutates the container.
    // We still emit a hidden TooltipProvider tree so future swaps to a
    // proper React-managed Tooltip can drop in without restructuring the
    // host. (See JSX below — kept inert behind aria-hidden.)
    return (
        <TooltipProvider>
            <div
                aria-hidden
                data-testid="comment-highlight-overlay"
                style={{ display: 'none' }}
            >
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span />
                    </TooltipTrigger>
                    <TooltipContent>highlight</TooltipContent>
                </Tooltip>
            </div>
        </TooltipProvider>
    );
}

function unwrapOverlayMarks(root: HTMLElement): void {
    const marks = root.querySelectorAll<HTMLElement>(
        `mark[${OVERLAY_ATTR}="true"]`,
    );

    marks.forEach((mark) => {
        const parent = mark.parentNode;

        if (!parent) {
            return;
        }

        while (mark.firstChild) {
            parent.insertBefore(mark.firstChild, mark);
        }

        parent.removeChild(mark);
        parent.normalize();
    });
}

function findAnchorRange(
    block: HTMLElement,
    quote: string,
    prefix: string,
    suffix: string,
): Range | null {
    if (quote.length === 0) {
        return null;
    }

    const text = block.textContent ?? '';

    // Disambiguate by prefix/suffix when present — pick the first occurrence
    // whose surrounding context matches.
    const candidates: number[] = [];
    let from = 0;

    while (from <= text.length) {
        const idx = text.indexOf(quote, from);

        if (idx < 0) {
            break;
        }

        candidates.push(idx);
        from = idx + 1;
    }

    if (candidates.length === 0) {
        return null;
    }

    let chosen = candidates[0];

    if (candidates.length > 1) {
        for (const candidate of candidates) {
            const candPrefix = text.slice(Math.max(0, candidate - prefix.length), candidate);
            const candSuffix = text.slice(candidate + quote.length, candidate + quote.length + suffix.length);

            if (
                (prefix.length === 0 || candPrefix === prefix) &&
                (suffix.length === 0 || candSuffix === suffix)
            ) {
                chosen = candidate;
                break;
            }
        }
    }

    // Translate the flat character offset back to a (textNode, offset) pair.
    return rangeFromFlatOffsets(block, chosen, chosen + quote.length);
}

function rangeFromFlatOffsets(block: HTMLElement, start: number, end: number): Range | null {
    const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
    let offset = 0;
    let startNode: Text | null = null;
    let startOffset = 0;
    let endNode: Text | null = null;
    let endOffset = 0;

    let node = walker.nextNode() as Text | null;

    while (node !== null) {
        const len = node.data.length;

        if (startNode === null && offset + len >= start) {
            startNode = node;
            startOffset = start - offset;
        }

        if (offset + len >= end) {
            endNode = node;
            endOffset = end - offset;
            break;
        }

        offset += len;
        node = walker.nextNode() as Text | null;
    }

    if (!startNode || !endNode) {
        return null;
    }

    try {
        const range = document.createRange();
        range.setStart(startNode, startOffset);
        range.setEnd(endNode, endOffset);

        return range;
    } catch {
        return null;
    }
}

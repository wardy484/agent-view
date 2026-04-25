import { useLayoutEffect } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { unwrapMarks, wrapRangeWithMarks } from '@/lib/selection-helpers';

/**
 * REQ-M6-028: persistent inline highlights for comments whose anchor
 * resolves on the current revision.
 *
 * Walks the report container in a `useLayoutEffect` and wraps each
 * resolved anchor (`anchor.resolved_in_current_version === true`) in a
 * `<mark data-comment-overlay="true" data-comment-id="…" data-status="…">`
 * element. Idempotent: existing overlay marks are unwrapped before a
 * fresh walk. Stale anchors render no mark.
 *
 * REQ-M6-035: wrapping previously shifted the document layout when an
 * anchor crossed element boundaries (paragraphs broke, words split mid-
 * letter through `<code>` / `<strong>` siblings). The overlay now uses
 * the shared `wrapRangeWithMarks` helper, which walks the range with a
 * TreeWalker and emits ONE inline `<mark>` per intersecting text-node
 * sub-range. All marks for one comment share the same `data-comment-id`,
 * so the click handler / tooltip contract is unchanged.
 */

export type HighlightCommentSummary = {
    id: number;
    block_id: string;
    status: 'open' | 'resolved' | 'wontfix' | 'stale';
    body: string;
    /** REQ-M6-033: kind drives styling alongside status. */
    kind: 'comment' | 'suggestion';
    /** REQ-M6-033: empty string on a suggestion = deletion. */
    proposed_text?: string;
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
const OVERLAY_SELECTOR = `mark[${OVERLAY_ATTR}="true"]`;

// REQ-M6-033: a deletion is a suggestion whose proposed_text is the empty
// string. Surfaced as a small helper so styling + tooltip can branch on it.
function isDeletion(comment: HighlightCommentSummary): boolean {
    return comment.kind === 'suggestion' && (comment.proposed_text ?? '') === '';
}

// REQ-M6-033: styling branches on (status, kind, proposed_text emptiness).
//   - kind=comment, status=open                  → yellow
//   - kind=suggestion, status=open, deletion     → red strike
//   - kind=suggestion, status=open, non-deletion → indigo
//   - status=resolved                            → muted slate strike
//   - status=wontfix                             → grey strike
function classForComment(comment: HighlightCommentSummary): string | null {
    if (comment.status === 'resolved') {
        return 'bg-slate-300/40 dark:bg-slate-700/40 line-through decoration-slate-500';
    }

    if (comment.status === 'wontfix') {
        return 'bg-zinc-300/30 dark:bg-zinc-700/30 line-through opacity-60';
    }

    if (comment.status === 'open') {
        if (comment.kind === 'suggestion') {
            return isDeletion(comment)
                ? 'bg-red-200/40 dark:bg-red-900/40 line-through decoration-red-500'
                : 'bg-indigo-200/40 dark:bg-indigo-900/40';
        }

        return 'bg-yellow-200/40 dark:bg-yellow-900/40';
    }

    // status === 'stale' — nothing to render; the anchor doesn't resolve.
    return null;
}

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

        let rafId: number | null = null;
        let observer: MutationObserver | null = null;

        const wrapPass = () => {
            // REQ-M6-036: briefly disconnect the observer so the overlay's
            // own DOM mutations (the marks it inserts and the unwrap pass
            // that precedes them) don't re-trigger schedule() in a
            // feedback loop. We reconnect at the end so subsequent
            // react-markdown commits still drive a re-wrap.
            if (observer) {
                observer.disconnect();
            }

            // Idempotent re-run: unwrap any prior overlay marks before walking.
            // REQ-M6-035: a single comment may have produced many sibling
            // marks; unwrapMarks handles them all and re-coalesces adjacent
            // text nodes via parent.normalize().
            unwrapOverlayMarks(container);

            const eligible = comments.filter(
                (c) =>
                    c.anchor.resolved_in_current_version === true &&
                    c.status !== 'stale' &&
                    classForComment(c) !== null,
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

                const cls = classForComment(comment);

                if (cls === null) {
                    continue;
                }

                // REQ-M6-033: tooltip surfaces the comment kind alongside the
                // existing author + first-line body.
                const firstLine = (comment.body.split(/\r?\n/)[0] ?? '').trim();
                const tooltipLabel = `${comment.author.display_name} (${comment.kind}): ${firstLine}`;

                // REQ-M6-035: per-text-node wrapping. wrapRangeWithMarks emits
                // one inline <mark> per text-node sub-range, all sharing the
                // same data-comment-id so the click handler + tooltip stay
                // consistent across siblings. The DOM-shifting Range APIs that
                // caused the layout regression this REQ fixes are not used.
                const marks = wrapRangeWithMarks(range, () => {
                    const mark = document.createElement('mark');
                    mark.setAttribute(OVERLAY_ATTR, 'true');
                    mark.setAttribute('data-comment-id', String(comment.id));
                    mark.setAttribute('data-status', comment.status);
                    mark.setAttribute('data-kind', comment.kind);

                    // REQ-M6-033: surface the deletion variant for downstream
                    // tests and styling debugging. Empty string (the deletion
                    // sentinel) is explicitly distinct from a missing attribute.
                    if (comment.kind === 'suggestion') {
                        mark.setAttribute(
                            'data-deletion',
                            isDeletion(comment) ? 'true' : 'false',
                        );
                    }

                    mark.className = `${cls} cursor-pointer rounded px-0.5 hover:ring-2 hover:ring-amber-400`;
                    mark.title = tooltipLabel;
                    mark.setAttribute('aria-label', tooltipLabel);
                    mark.setAttribute('data-tooltip', tooltipLabel);

                    mark.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        onCommentClick(comment.id);
                    });

                    return mark;
                });

                void marks;
            }

            // Reconnect so subsequent react-markdown commits still trigger
            // a re-wrap on the next paint.
            if (observer) {
                observer.observe(container, { childList: true, subtree: true });
            }
        };

        const apply = () => {
            rafId = null;
            wrapPass();
        };

        const schedule = () => {
            if (rafId !== null) {
                return;
            }

            rafId = requestAnimationFrame(apply);
        };

        // REQ-M6-036: first paint may already have committed react-markdown's
        // children for SSR / fast hydration; schedule an initial wrap so we
        // don't depend on the observer firing.
        schedule();

        // REQ-M6-036: react-markdown commits children async — observe the
        // container and re-wrap on every batched paint. childList + subtree
        // catch every new descendant. requestAnimationFrame coalesces
        // bursts to one re-wrap per paint.
        observer = new MutationObserver(schedule);
        observer.observe(container, { childList: true, subtree: true });

        return () => {
            // Cleanup on unmount or before re-run.
            if (rafId !== null) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }

            if (observer) {
                observer.disconnect();
                observer = null;
            }

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
    // REQ-M6-035: delegate to the shared unwrapMarks so adjacent text
    // nodes are coalesced (parent.normalize()) — keeping the DOM identical
    // to its pre-overlay shape between re-runs. The selector matches every
    // overlay mark, including the multiple siblings produced for a single
    // comment whose anchor crosses element boundaries.
    unwrapMarks(root, OVERLAY_SELECTOR);
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

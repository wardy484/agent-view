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
    return (
        comment.kind === 'suggestion' && (comment.proposed_text ?? '') === ''
    );
}

// REQ-M6-033: styling branches on (status, kind, proposed_text emptiness).
//   - kind=comment, status=open                  → yellow
//   - kind=suggestion, status=open, deletion     → red strike
//   - kind=suggestion, status=open, non-deletion → indigo
//   - status=resolved                            → muted slate strike
//   - status=wontfix                             → grey strike
//
// REQ-M6-037: tints bumped from /40 to /60-/70 with a 2px dotted bottom
// border so highlights remain visible on light and dark backgrounds even
// when the tint blends with the page.
function classForComment(comment: HighlightCommentSummary): string | null {
    if (comment.status === 'resolved') {
        return 'bg-slate-300/60 dark:bg-slate-700/60 line-through decoration-slate-500 border-b-2 border-dotted border-slate-400';
    }

    if (comment.status === 'wontfix') {
        return 'bg-zinc-300/50 dark:bg-zinc-700/50 line-through opacity-70 border-b-2 border-dotted border-zinc-400';
    }

    if (comment.status === 'open') {
        if (comment.kind === 'suggestion') {
            return isDeletion(comment)
                ? 'bg-red-200/70 dark:bg-red-900/60 line-through decoration-red-500 border-b-2 border-dotted border-red-400'
                : 'bg-indigo-200/70 dark:bg-indigo-900/50 border-b-2 border-dotted border-indigo-400';
        }

        return 'bg-yellow-200/70 dark:bg-yellow-700/40 border-b-2 border-dotted border-yellow-500';
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
        // REQ-M6-040: do NOT capture containerRef.current at effect-start.
        // React's commit order attaches a parent's ref AFTER a child's
        // useLayoutEffect fires, so the child saw `null` on first mount and
        // returned early before any trigger could run. Resolve the ref lazily
        // inside each wrapPass so the poll loop can retry until the ref
        // attaches (typically within 1-2 frames of mount).

        // REQ-M6-039: opt-in diagnostic gated on the ?debug-highlights URL flag.
        const debug =
            typeof window !== 'undefined' &&
            new URLSearchParams(window.location.search).has('debug-highlights');

        if (debug) {
            console.log('[highlight-overlay] effect started', {
                commentsLength: comments.length,
                hasContainer: !!containerRef.current,
            });
        }

        let rafId: number | null = null;
        let pollId: ReturnType<typeof setTimeout> | null = null;
        let observer: MutationObserver | null = null;
        const pollStart = Date.now();
        let unmounted = false;

        const wrapPass = (): number => {
            // REQ-M6-040: read containerRef lazily — the parent's ref may not
            // have been attached yet on the very first call.
            const container = containerRef.current;

            if (!container || unmounted) {
                return 0;
            }

            // REQ-M6-036 / REQ-M6-038: briefly disconnect the observer so
            // the overlay's own DOM mutations don't re-trigger schedule()
            // in a feedback loop. Reconnect at the end so subsequent
            // react-markdown commits still drive a re-wrap.
            if (observer) {
                observer.disconnect();
            }

            // Idempotent re-run: unwrap any prior overlay marks before walking.
            unwrapOverlayMarks(container);

            const eligible = comments.filter(
                (c) =>
                    c.anchor.resolved_in_current_version === true &&
                    c.status !== 'stale' &&
                    classForComment(c) !== null,
            );

            // REQ-M6-039: opt-in diagnostic at the top of every wrapPass.
            if (debug) {
                const blockEls = container.querySelectorAll(
                    '[data-comment-block-id]',
                );

                console.log('[highlight-overlay] wrapPass', {
                    trigger:
                        new Error().stack?.split('\n')[2]?.trim() ?? 'unknown',
                    commentsTotal: comments.length,
                    eligibleCount: eligible.length,
                    blocksInDom: blockEls.length,
                    blockIdsInDom: [...blockEls].map((el) =>
                        el.getAttribute('data-comment-block-id'),
                    ),
                    firstEligibleBlockId: eligible[0]?.block_id ?? null,
                    firstEligibleQuotePreview:
                        eligible[0]?.anchor.quote.slice(0, 40) ?? null,
                    firstEligibleResolvedFlag:
                        eligible[0]?.anchor.resolved_in_current_version ?? null,
                    firstEligibleStatus: eligible[0]?.status ?? null,
                });
            }

            let wrapped = 0;

            for (const comment of eligible) {
                const block = container.querySelector<HTMLElement>(
                    `[data-comment-block-id="${comment.block_id}"]`,
                );

                if (!block) {
                    if (debug) {
                        console.log('[highlight-overlay] block not found', {
                            commentId: comment.id,
                            blockId: comment.block_id,
                        });
                    }

                    continue;
                }

                let range = findAnchorRange(
                    block,
                    comment.anchor.quote,
                    comment.anchor.prefix ?? '',
                    comment.anchor.suffix ?? '',
                );

                range ??= findCrossBlockAnchorRange(
                    container,
                    block,
                    comment.anchor.quote,
                    comment.anchor.prefix ?? '',
                    comment.anchor.suffix ?? '',
                );

                if (!range) {
                    if (debug) {
                        console.log('[highlight-overlay] range not found', {
                            commentId: comment.id,
                            quote: comment.anchor.quote.slice(0, 40),
                            blockText: block.textContent?.slice(0, 100),
                        });
                    }

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

                // REQ-M6-035: per-text-node wrapping.
                const marks = wrapRangeWithMarks(range, () => {
                    const mark = document.createElement('mark');
                    mark.setAttribute(OVERLAY_ATTR, 'true');
                    mark.setAttribute('data-comment-id', String(comment.id));
                    mark.setAttribute('data-status', comment.status);
                    mark.setAttribute('data-kind', comment.kind);

                    if (comment.kind === 'suggestion') {
                        mark.setAttribute(
                            'data-deletion',
                            isDeletion(comment) ? 'true' : 'false',
                        );
                    }

                    // REQ-M9-010: hover ring uses the M9 `--ring` token so it
                    // tracks the rest of the design system instead of a raw
                    // amber swatch. The tint classes themselves are pinned
                    // by REQ-M6-037 and must not change here.
                    mark.className = `${cls} cursor-pointer rounded-sm px-0.5 transition-shadow hover:ring-2 hover:ring-ring hover:ring-offset-1`;
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

                if (marks.length > 0) {
                    wrapped += marks.length;
                }
            }

            if (debug) {
                console.log('[highlight-overlay] wrapPass result', { wrapped });
            }

            if (observer && !unmounted) {
                observer.observe(container, { childList: true, subtree: true });
            }

            return wrapped;
        };

        // REQ-M6-038 (a): immediate synchronous wrap. If react-markdown's
        // children have already committed (typical for SSR / fast hydration),
        // this nails first paint with no extra frame delay.
        let wrapped = wrapPass();

        // REQ-M6-038 (b): next-frame retry. react-markdown often commits its
        // children on the very next microtask, so a single rAF picks them up.
        if (wrapped === 0) {
            rafId = requestAnimationFrame(() => {
                rafId = null;
                wrapped = wrapPass();
            });
        }

        // REQ-M6-038 (c): 250ms poll up to 5s, or until any wrap succeeds.
        // Covers slow hydration / async react-markdown commits / Inertia v3
        // deferred-prop arrival on production builds where the rAF alone
        // wasn't reliably winning the race against the user's perception.
        const startPoll = (): void => {
            pollId = setTimeout(() => {
                pollId = null;

                if (unmounted) {
                    return;
                }

                const w = wrapPass();

                if (w > 0) {
                    return; // success — observer takes over from here
                }

                if (Date.now() - pollStart > 5000) {
                    return; // give up; observer remains active
                }

                startPoll();
            }, 250);
        };

        startPoll();

        // REQ-M6-036 / REQ-M6-040: long-lived MutationObserver. Attached
        // lazily via the poll loop's first successful wrapPass, since the
        // container ref may be null on first mount.
        const ensureObserver = () => {
            const c = containerRef.current;

            if (!c || observer) {
                return;
            }

            observer = new MutationObserver(() => {
                if (rafId !== null) {
                    return;
                }

                rafId = requestAnimationFrame(() => {
                    rafId = null;
                    wrapPass();
                });
            });
            observer.observe(c, { childList: true, subtree: true });
        };
        ensureObserver();

        // Re-attempt observer attachment on each animation frame for the
        // first second — this covers the React commit-order edge case where
        // the parent's ref attaches on a later frame than the child's effect.
        let observerAttachTries = 0;
        const observerAttachInterval = setInterval(() => {
            observerAttachTries += 1;
            ensureObserver();

            if (observer || observerAttachTries > 20) {
                clearInterval(observerAttachInterval);
            }
        }, 50);

        return () => {
            // Cleanup on unmount or before re-run. REQ-M6-038: tear down all
            // three trigger types (rAF, timeout, observer).
            unmounted = true;

            clearInterval(observerAttachInterval);

            if (rafId !== null) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }

            if (pollId !== null) {
                clearTimeout(pollId);
                pollId = null;
            }

            if (observer) {
                observer.disconnect();
                observer = null;
            }

            const c = containerRef.current;

            if (c && c.isConnected) {
                unwrapOverlayMarks(c);
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
            const candPrefix = text.slice(
                Math.max(0, candidate - prefix.length),
                candidate,
            );
            const candSuffix = text.slice(
                candidate + quote.length,
                candidate + quote.length + suffix.length,
            );

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

function rangeFromFlatOffsets(
    block: HTMLElement,
    start: number,
    end: number,
): Range | null {
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

function findCrossBlockAnchorRange(
    container: HTMLElement,
    startBlock: HTMLElement,
    quote: string,
    prefix: string,
    suffix: string,
): Range | null {
    const normalizedQuote = quote.replace(/\r?\n+/g, '');

    if (normalizedQuote.length === 0) {
        return null;
    }

    const textNodes = collectTextNodesFromBlock(container, startBlock);
    let text = '';

    for (const node of textNodes) {
        text += node.data;
    }

    const candidates: number[] = [];
    let from = 0;

    while (from <= text.length) {
        const idx = text.indexOf(normalizedQuote, from);

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
            const candPrefix = text.slice(
                Math.max(0, candidate - prefix.length),
                candidate,
            );
            const candSuffix = text.slice(
                candidate + normalizedQuote.length,
                candidate + normalizedQuote.length + suffix.length,
            );

            if (
                (prefix.length === 0 || candPrefix === prefix) &&
                (suffix.length === 0 || candSuffix === suffix)
            ) {
                chosen = candidate;
                break;
            }
        }
    }

    return rangeFromTextNodes(textNodes, chosen, chosen + normalizedQuote.length);
}

function collectTextNodesFromBlock(
    container: HTMLElement,
    startBlock: HTMLElement,
): Text[] {
    const nodes: Text[] = [];
    let collecting = false;
    const blocks = container.querySelectorAll<HTMLElement>(
        '[data-comment-block-id]',
    );

    blocks.forEach((block) => {
        if (block === startBlock) {
            collecting = true;
        }

        if (!collecting) {
            return;
        }

        const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
        let node = walker.nextNode() as Text | null;

        while (node !== null) {
            nodes.push(node);
            node = walker.nextNode() as Text | null;
        }
    });

    return nodes;
}

function rangeFromTextNodes(
    nodes: Text[],
    start: number,
    end: number,
): Range | null {
    let offset = 0;
    let startNode: Text | null = null;
    let startOffset = 0;
    let endNode: Text | null = null;
    let endOffset = 0;

    for (const node of nodes) {
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

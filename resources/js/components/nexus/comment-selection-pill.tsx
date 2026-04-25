import { Copy, MessageSquare, PenLine } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import type { SelectionInfo } from '@/hooks/use-markdown-selection';
import { captureSelection } from '@/lib/selection-helpers';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-023: compact floating pill anchored to the selection's bounding
 * rect. Replaces the M6-013 floating menu and the M6-021/022 sticky bottom
 * toolbar (both removed). Three icon-only buttons (Comment, Suggest edit,
 * Copy). Cross-block selections disable Comment + Suggest. The pill mounts
 * only when there is a non-collapsed selection inside the report container.
 *
 * Positioning:
 *  - On screens ≥ Tailwind `lg` (≥1024px), the pill renders ABOVE the
 *    selection (`top = rect.top - pillHeight - 8`) — the historic desktop
 *    behaviour from REQ-M6-013.
 *  - On screens < `lg`, the pill renders BELOW the selection
 *    (`top = rect.bottom + 8`) so it does not fight the OS-rendered
 *    selection bubble that lives above the highlight on Android Chrome /
 *    iOS Safari.
 *
 * Horizontal placement is clamped to the viewport so the pill never
 * overflows the screen edge.
 *
 * Click handlers re-read `window.getSelection()` via `captureSelection` at
 * tap time rather than trusting potentially-stale React state — this is
 * the M6-022 fix carried forward.
 *
 * On scroll, the selection rect goes stale immediately; the pill clears
 * the selection rather than chasing the rect (matches Medium / Notion).
 */

type Props = {
    selection: SelectionInfo | null;
    /** Container element to scope click-time captureSelection() to. */
    containerRef: React.RefObject<HTMLElement | null>;
    onComment: (selection: SelectionInfo) => void;
    onSuggest: (selection: SelectionInfo) => void;
    onClose: () => void;
    /** REQ-M6-015 parity: disables Comment + Suggest in historical view. */
    readOnly?: boolean;
};

const PILL_HEIGHT = 40;
const PILL_GAP = 8;
const FALLBACK_PILL_WIDTH = 132;
const VIEWPORT_PADDING = 8;
const LG_BREAKPOINT_PX = 1024;

const CROSS_BLOCK_HINT = 'Selection must stay within one block.';
const READ_ONLY_HINT = 'Read-only — switch to the latest revision to comment.';

export function CommentSelectionPill({
    selection,
    containerRef,
    onComment,
    onSuggest,
    onClose,
    readOnly = false,
}: Props) {
    const [pillEl, setPillEl] = useState<HTMLDivElement | null>(null);
    const [, copy] = useClipboard();
    const [isWide, setIsWide] = useState<boolean>(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return true;
        }

        return window.matchMedia(`(min-width: ${LG_BREAKPOINT_PX}px)`).matches;
    });

    const pillRef = useCallback((node: HTMLDivElement | null) => {
        setPillEl(node);
    }, []);

    // REQ-M6-023: track viewport breakpoint via window.matchMedia so the
    // position offset can flip ABOVE / BELOW at runtime.
    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return;
        }

        const mql = window.matchMedia(`(min-width: ${LG_BREAKPOINT_PX}px)`);

        const onChange = (event: MediaQueryListEvent) => {
            setIsWide(event.matches);
        };

        // Reconcile in case the breakpoint changed between initial render
        // and effect attach. We schedule on the next microtask so React
        // doesn't flag this as a setState-in-effect anti-pattern.
        if (mql.matches !== isWide) {
            queueMicrotask(() => setIsWide(mql.matches));
        }

        if (typeof mql.addEventListener === 'function') {
            mql.addEventListener('change', onChange);

            return () => mql.removeEventListener('change', onChange);
        }

        // Older Safari fallback.
        mql.addListener(onChange);

        return () => mql.removeListener(onChange);
    }, []);

    // Escape closes the pill (consistent with the prior menu/toolbar).
    useEffect(() => {
        if (!selection) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [selection, onClose]);

    // REQ-M6-023: auto-hide on scroll. The selection rect captured at
    // selectionchange goes stale immediately on scroll, so we clear the
    // selection rather than try to chase it. Matches Medium / Notion.
    useEffect(() => {
        if (!selection) {
            return;
        }

        const onScroll = () => {
            onClose();
        };

        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, [selection, onClose]);

    if (!selection) {
        return null;
    }

    const { rect, blockId, quote } = selection;
    const crossesBlocks = blockId === null;

    const measuredWidth = pillEl?.offsetWidth ?? FALLBACK_PILL_WIDTH;
    const measuredHeight = pillEl?.offsetHeight ?? PILL_HEIGHT;

    // REQ-M6-023: above on lg+, below on smaller screens. The OS selection
    // bubble on Android Chrome / iOS Safari sits above the highlight, so
    // BELOW keeps our pill out of its way.
    const top = isWide
        ? rect.top + window.scrollY - measuredHeight - PILL_GAP
        : rect.bottom + window.scrollY + PILL_GAP;

    // Centre over the selection horizontally, then clamp to the viewport.
    const viewportWidth =
        typeof window !== 'undefined' ? window.innerWidth : measuredWidth + VIEWPORT_PADDING * 2;
    const idealLeft = rect.left + window.scrollX + rect.width / 2 - measuredWidth / 2;
    const maxLeft = viewportWidth - measuredWidth - VIEWPORT_PADDING + window.scrollX;
    const left = Math.max(VIEWPORT_PADDING + window.scrollX, Math.min(idealLeft, maxLeft));

    // REQ-M6-023: re-read window.getSelection() at click time. The OS
    // selection bubble has finalised the selection by the time the user
    // taps a button — no event-timing race.
    const captureForAction = (): SelectionInfo | null => {
        const fresh = captureSelection(containerRef.current);

        if (fresh && fresh.blockId !== null) {
            return fresh;
        }

        return blockId !== null ? selection : null;
    };

    const handleComment = () => {
        const info = captureForAction();

        if (info && info.blockId !== null) {
            onComment(info);
        }
    };

    const handleSuggest = () => {
        const info = captureForAction();

        if (info && info.blockId !== null) {
            onSuggest(info);
        }
    };

    const handleCopy = async () => {
        const info = captureSelection(containerRef.current) ?? selection;

        if (!info) {
            return;
        }

        await copy(info.quote || quote);
        onClose();
    };

    return (
        <TooltipProvider>
            <div
                ref={pillRef}
                role="toolbar"
                aria-label="Selection actions"
                tabIndex={-1}
                data-testid="comment-selection-pill"
                data-cross-block={crossesBlocks ? 'true' : 'false'}
                data-position={isWide ? 'above' : 'below'}
                className={cn(
                    'absolute z-50 flex h-10 items-center gap-1 rounded-full border border-border bg-popover px-1 text-popover-foreground shadow-md',
                )}
                style={{ top, left }}
            >
                <PillButton
                    label="Comment"
                    icon={<MessageSquare className="size-4" aria-hidden />}
                    disabled={crossesBlocks || readOnly}
                    disabledHint={readOnly ? READ_ONLY_HINT : CROSS_BLOCK_HINT}
                    onClick={handleComment}
                    testId="comment-selection-pill-comment"
                />

                <PillButton
                    label="Suggest edit"
                    icon={<PenLine className="size-4" aria-hidden />}
                    disabled={crossesBlocks || readOnly}
                    disabledHint={readOnly ? READ_ONLY_HINT : CROSS_BLOCK_HINT}
                    onClick={handleSuggest}
                    testId="comment-selection-pill-suggest"
                />

                <PillButton
                    label="Copy"
                    icon={<Copy className="size-4" aria-hidden />}
                    disabled={false}
                    onClick={handleCopy}
                    testId="comment-selection-pill-copy"
                />
            </div>
        </TooltipProvider>
    );
}

type PillButtonProps = {
    label: string;
    icon: React.ReactNode;
    disabled: boolean;
    disabledHint?: string;
    onClick: () => void;
    testId: string;
};

function PillButton({ label, icon, disabled, disabledHint, onClick, testId }: PillButtonProps) {
    const button = (
        <button
            type="button"
            disabled={disabled}
            data-testid={testId}
            aria-label={label}
            aria-disabled={disabled}
            title={disabled && disabledHint ? disabledHint : label}
            onClick={onClick}
            className={cn(
                'inline-flex size-8 items-center justify-center rounded-full transition-colors',
                disabled
                    ? 'cursor-not-allowed text-muted-foreground opacity-50'
                    : 'hover:bg-accent hover:text-accent-foreground active:bg-accent/80',
            )}
        >
            {icon}
        </button>
    );

    if (disabled && disabledHint) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex">{button}</span>
                </TooltipTrigger>
                <TooltipContent side="top">{disabledHint}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{button}</TooltipTrigger>
            <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
    );
}

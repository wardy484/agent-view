import { router } from '@inertiajs/react';
import { Copy, MessageSquare, PenLine } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import type { SelectionInfo } from '@/hooks/use-markdown-selection';
import {
    captureSelection,
    removeSyntheticHighlight,
    synthesizeHighlight,
} from '@/lib/selection-helpers';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-023: compact floating pill anchored to the selection's bounding
 * rect. Replaces the M6-013 floating menu and the M6-021/022 sticky bottom
 * toolbar (both removed). Three icon-only buttons (Comment, Suggest edit,
 * Copy). Cross-block selections disable Comment + Suggest. The pill mounts
 * only when there is a non-collapsed selection inside the report container.
 *
 * Positioning:
 *  - On screens >= Tailwind `lg` (>=1024px), the pill renders ABOVE the
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
 *
 * REQ-M6-024: the pill uses `position: fixed` rather than `position:
 * absolute`. The report container has a `position: relative` ancestor,
 * which would otherwise resolve `absolute` coordinates against the
 * container's own offset parent and push the pill off-screen. With
 * `fixed`, `getBoundingClientRect()` already returns viewport-relative
 * coordinates — no document-scroll offsets needed.
 *
 * REQ-M6-027: tapping Comment or Suggest expands the pill inline into a
 * composer rather than routing through the sidebar drawer. The expanded
 * pill grows in height to host a textarea (and a "proposed text" textarea
 * for Suggest), Submit + Cancel buttons. The captured range is wrapped in
 * a synthetic `<mark data-pending-anchor>` so the selected text remains
 * visibly highlighted while the user types. Submit POSTs to
 * `/snapshots/{snapshot}/comments` and reloads only the `comments` prop;
 * Cancel/Escape removes the synthetic mark and resets to idle.
 */

type Mode = 'idle' | 'composing-comment' | 'composing-suggestion';

type Props = {
    selection: SelectionInfo | null;
    /** Container element to scope click-time captureSelection() to. */
    containerRef: React.RefObject<HTMLElement | null>;
    onComment: (selection: SelectionInfo) => void;
    onSuggest: (selection: SelectionInfo) => void;
    onClose: () => void;
    /** REQ-M6-015 parity: disables Comment + Suggest in historical view. */
    readOnly?: boolean;
    /** REQ-M6-027: required for the inline composer to POST to the right endpoint. */
    snapshotId?: number;
};

const PILL_HEIGHT = 40;
const PILL_GAP = 8;
const FALLBACK_PILL_WIDTH = 132;
const VIEWPORT_PADDING = 8;
const LG_BREAKPOINT_PX = 1024;
const COMPOSER_FALLBACK_WIDTH = 320;

const CROSS_BLOCK_HINT = 'Selection must stay within one block.';
const READ_ONLY_HINT = 'Read-only — switch to the latest revision to comment.';

export function CommentSelectionPill({
    selection,
    containerRef,
    onComment,
    onSuggest,
    onClose,
    readOnly = false,
    snapshotId,
}: Props) {
    const [pillEl, setPillEl] = useState<HTMLDivElement | null>(null);
    const [, copy] = useClipboard();
    const [mode, setMode] = useState<Mode>('idle');
    const [body, setBody] = useState('');
    const [proposedText, setProposedText] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pendingAnchorRect, setPendingAnchorRect] = useState<DOMRect | null>(null);
    const [captured, setCaptured] = useState<SelectionInfo | null>(null);
    const markRef = useRef<HTMLElement | null>(null);

    const [isWide, setIsWide] = useState<boolean>(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return true;
        }

        return window.matchMedia(`(min-width: ${LG_BREAKPOINT_PX}px)`).matches;
    });

    const pillRef = useCallback((node: HTMLDivElement | null) => {
        setPillEl(node);
    }, []);

    const composing = mode !== 'idle';

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return;
        }

        const mql = window.matchMedia(`(min-width: ${LG_BREAKPOINT_PX}px)`);

        const onChange = (event: MediaQueryListEvent) => {
            setIsWide(event.matches);
        };

        if (mql.matches !== isWide) {
            queueMicrotask(() => setIsWide(mql.matches));
        }

        if (typeof mql.addEventListener === 'function') {
            mql.addEventListener('change', onChange);

            return () => mql.removeEventListener('change', onChange);
        }

        mql.addListener(onChange);

        return () => mql.removeListener(onChange);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // REQ-M6-027: reset composer state and unwrap the synthetic mark.
    const resetComposer = useCallback(() => {
        removeSyntheticHighlight();
        markRef.current = null;
        setCaptured(null);
        setMode('idle');
        setBody('');
        setProposedText('');
        setError(null);
        setSubmitting(false);
        setPendingAnchorRect(null);
    }, []);

    useEffect(() => {
        if (!selection && !composing) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                if (composing) {
                    resetComposer();
                }

                onClose();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [selection, composing, onClose, resetComposer]);

    useEffect(() => {
        if (!selection || composing) {
            return;
        }

        const onScroll = () => {
            onClose();
        };

        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, [selection, composing, onClose]);

    useEffect(() => {
        if (!composing) {
            return;
        }

        const recompute = () => {
            if (markRef.current) {
                setPendingAnchorRect(markRef.current.getBoundingClientRect());
            }
        };

        window.addEventListener('resize', recompute);
        window.addEventListener('scroll', recompute, { passive: true });

        return () => {
            window.removeEventListener('resize', recompute);
            window.removeEventListener('scroll', recompute);
        };
    }, [composing]);

    useEffect(() => {
        if (!selection && !composing) {
            removeSyntheticHighlight();
        }
    }, [selection, composing]);

    if (!selection && !composing) {
        return null;
    }

    const activeRect = pendingAnchorRect ?? selection?.rect ?? null;

    if (!activeRect) {
        return null;
    }

    const blockId = composing
        ? captured?.blockId ?? null
        : selection?.blockId ?? null;
    const quote = composing
        ? captured?.quote ?? ''
        : selection?.quote ?? '';
    const crossesBlocks = blockId === null;

    const measuredWidth = pillEl?.offsetWidth ?? (composing ? COMPOSER_FALLBACK_WIDTH : FALLBACK_PILL_WIDTH);
    const measuredHeight = pillEl?.offsetHeight ?? PILL_HEIGHT;

    let top = isWide
        ? activeRect.top - measuredHeight - PILL_GAP
        : activeRect.bottom + PILL_GAP;

    const viewportWidth =
        typeof window !== 'undefined' ? window.innerWidth : measuredWidth + VIEWPORT_PADDING * 2;
    const viewportHeight =
        typeof window !== 'undefined' ? window.innerHeight : measuredHeight + VIEWPORT_PADDING * 2;

    const idealLeft = activeRect.left + activeRect.width / 2 - measuredWidth / 2;
    const maxLeft = viewportWidth - measuredWidth - VIEWPORT_PADDING;
    const left = Math.max(VIEWPORT_PADDING, Math.min(idealLeft, maxLeft));

    if (composing) {
        const maxTop = viewportHeight - measuredHeight - VIEWPORT_PADDING;
        top = Math.max(VIEWPORT_PADDING, Math.min(top, maxTop));
    }

    const captureForAction = (): SelectionInfo | null => {
        const fresh = captureSelection(containerRef.current);

        if (fresh && fresh.blockId !== null) {
            return fresh;
        }

        return selection && selection.blockId !== null ? selection : null;
    };

    const beginCompose = (kind: 'comment' | 'suggestion') => {
        const info = captureForAction();

        if (!info || info.blockId === null) {
            return;
        }

        const sel = window.getSelection();

        if (!sel || sel.rangeCount === 0) {
            return;
        }

        const range = sel.getRangeAt(0).cloneRange();
        const mark = synthesizeHighlight(range);

        if (!mark) {
            return;
        }

        // Clear the live OS selection so the OS bubble vanishes.
        window.getSelection()?.removeAllRanges();

        markRef.current = mark;
        setCaptured(info);
        setPendingAnchorRect(mark.getBoundingClientRect());
        setMode(kind === 'comment' ? 'composing-comment' : 'composing-suggestion');
        setError(null);

        if (kind === 'comment') {
            onComment(info);
        } else {
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

    const handleCancel = () => {
        resetComposer();
        onClose();
    };

    const handleSubmit = () => {
        const info = captured;

        if (!info || info.blockId === null || snapshotId === undefined) {
            setError('Cannot submit — selection lost.');

            return;
        }

        if (body.trim().length === 0) {
            setError('Body is required.');

            return;
        }

        if (mode === 'composing-suggestion' && proposedText.trim().length === 0) {
            setError('Proposed text is required for a suggestion.');

            return;
        }

        const kind = mode === 'composing-suggestion' ? 'suggestion' : 'comment';

        setSubmitting(true);
        setError(null);

        router.post(
            `/snapshots/${snapshotId}/comments`,
            {
                block_id: info.blockId,
                kind,
                body: body.trim(),
                proposed_text: kind === 'suggestion' ? proposedText.trim() : undefined,
                anchor_quote: info.quote,
                anchor_prefix: info.prefix,
                anchor_suffix: info.suffix,
                anchor_start_hint: info.startHint,
                anchor_end_hint: info.endHint,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['comments'],
                onSuccess: () => {
                    resetComposer();
                    onClose();
                },
                onError: (errors: Record<string, string>) => {
                    const messages = Object.values(errors);
                    setError(messages[0] ?? 'Failed to post comment.');
                    setSubmitting(false);
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const composerMaxDimension = Math.min(viewportWidth, viewportHeight) - VIEWPORT_PADDING * 2;
    const composerWidth = Math.min(360, composerMaxDimension);

    return (
        <TooltipProvider>
            <div
                ref={pillRef}
                role="toolbar"
                aria-label={composing ? 'Comment composer' : 'Selection actions'}
                tabIndex={-1}
                data-testid="comment-selection-pill"
                data-cross-block={crossesBlocks ? 'true' : 'false'}
                data-position={isWide ? 'above' : 'below'}
                data-mode={mode}
                className={cn(
                    'fixed z-50 flex items-center gap-1 rounded-2xl border border-border bg-popover text-popover-foreground shadow-md',
                    composing
                        ? 'flex-col items-stretch p-3'
                        : 'h-10 px-1',
                )}
                style={{
                    top,
                    left,
                    maxHeight: composerMaxDimension,
                    maxWidth: composerMaxDimension,
                    width: composing ? composerWidth : undefined,
                }}
            >
                {!composing ? (
                    <>
                        <PillButton
                            label="Comment"
                            icon={<MessageSquare className="size-4" aria-hidden />}
                            disabled={crossesBlocks || readOnly}
                            disabledHint={readOnly ? READ_ONLY_HINT : CROSS_BLOCK_HINT}
                            onClick={() => beginCompose('comment')}
                            testId="comment-selection-pill-comment"
                        />

                        <PillButton
                            label="Suggest edit"
                            icon={<PenLine className="size-4" aria-hidden />}
                            disabled={crossesBlocks || readOnly}
                            disabledHint={readOnly ? READ_ONLY_HINT : CROSS_BLOCK_HINT}
                            onClick={() => beginCompose('suggestion')}
                            testId="comment-selection-pill-suggest"
                        />

                        <PillButton
                            label="Copy"
                            icon={<Copy className="size-4" aria-hidden />}
                            disabled={false}
                            onClick={handleCopy}
                            testId="comment-selection-pill-copy"
                        />
                    </>
                ) : (
                    <ComposerBody
                        mode={mode}
                        body={body}
                        proposedText={proposedText}
                        error={error}
                        submitting={submitting}
                        onBodyChange={setBody}
                        onProposedTextChange={setProposedText}
                        onSubmit={handleSubmit}
                        onCancel={handleCancel}
                    />
                )}
            </div>
        </TooltipProvider>
    );
}

type ComposerBodyProps = {
    mode: Mode;
    body: string;
    proposedText: string;
    error: string | null;
    submitting: boolean;
    onBodyChange: (value: string) => void;
    onProposedTextChange: (value: string) => void;
    onSubmit: () => void;
    onCancel: () => void;
};

function ComposerBody({
    mode,
    body,
    proposedText,
    error,
    submitting,
    onBodyChange,
    onProposedTextChange,
    onSubmit,
    onCancel,
}: ComposerBodyProps) {
    const isSuggestion = mode === 'composing-suggestion';

    const bodyRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        bodyRef.current?.focus();
    }, []);

    return (
        <div
            className="flex flex-col gap-2"
            data-testid="comment-selection-pill-composer"
            data-composer-kind={isSuggestion ? 'suggestion' : 'comment'}
        >
            <label className="text-xs font-medium text-muted-foreground">
                {isSuggestion ? 'Suggestion' : 'Comment'}
            </label>
            <textarea
                ref={bodyRef}
                value={body}
                onChange={(e) => onBodyChange(e.target.value)}
                disabled={submitting}
                placeholder={isSuggestion ? 'Why this change?' : 'Add a comment'}
                rows={3}
                data-testid="comment-selection-pill-body"
                className="w-full resize-none rounded-md border border-border bg-background px-2 py-1.5 text-sm focus:border-primary focus:outline-none disabled:opacity-60"
            />

            {isSuggestion ? (
                <>
                    <label className="text-xs font-medium text-muted-foreground">
                        Proposed text
                    </label>
                    <textarea
                        value={proposedText}
                        onChange={(e) => onProposedTextChange(e.target.value)}
                        disabled={submitting}
                        placeholder="Replacement text"
                        rows={3}
                        data-testid="comment-selection-pill-proposed"
                        className="w-full resize-none rounded-md border border-border bg-background px-2 py-1.5 text-sm focus:border-primary focus:outline-none disabled:opacity-60"
                    />
                </>
            ) : null}

            {error ? (
                <p
                    role="alert"
                    data-testid="comment-selection-pill-error"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            ) : null}

            <div className="flex items-center justify-end gap-2">
                <button
                    type="button"
                    data-testid="comment-selection-pill-cancel"
                    onClick={onCancel}
                    disabled={submitting}
                    className="rounded-md border border-border bg-background px-3 py-1 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground disabled:opacity-60"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    data-testid="comment-selection-pill-submit"
                    onClick={onSubmit}
                    disabled={submitting}
                    className="rounded-md bg-primary px-3 py-1 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                >
                    {submitting ? 'Posting…' : 'Submit'}
                </button>
            </div>
        </div>
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

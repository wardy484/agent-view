import { Copy, MessageSquare, MousePointerSquareDashed, PenLine } from 'lucide-react';
import { useEffect } from 'react';

import { useClipboard } from '@/hooks/use-clipboard';
import type { SelectionInfo } from '@/hooks/use-markdown-selection';
import { captureSelection } from '@/lib/selection-helpers';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-021 / REQ-M6-022: mobile counterpart to REQ-M6-013's floating
 * selection menu.
 *
 * On phones (and other viewports below Tailwind's `lg` breakpoint), the
 * floating menu fights iOS Safari's native selection bubble and frequently
 * misses touch `mouseup` events. This component renders a sticky bottom
 * toolbar instead — touch-friendly hit targets, fixed below the selection
 * bubble, with the same three actions (`Comment`, `Suggest edit`, `Copy`).
 *
 * REQ-M6-022: the toolbar is **always visible** below `lg`. When no
 * selection is active it renders a muted "Highlight text to comment"
 * empty state with action buttons disabled. When the user taps Comment
 * or Suggest, the handler reads `window.getSelection()` directly via
 * `captureSelection` — bypassing the React-state path that suffered from
 * a touch timing race on Android Chrome / iOS Safari (selectionchange
 * fires many times during the drag and the hook's prior debounce raced
 * the late-arriving valid selection).
 *
 * The desktop floating menu (above `lg`) is unchanged; `report-view.tsx`
 * gates each renderer with `hidden lg:block` / `lg:hidden` wrappers so
 * exactly one is visible per breakpoint.
 */

type Props = {
    /** Reactive selection from `useMarkdownSelection` (visual feedback only). */
    selection: SelectionInfo | null;
    /** Container element to scope click-time captureSelection() to. */
    containerRef: React.RefObject<HTMLElement | null>;
    onComment: (selection: SelectionInfo) => void;
    onSuggest: (selection: SelectionInfo) => void;
    onClose: () => void;
    /** REQ-M6-015 parity: disables Comment + Suggest in historical view. */
    readOnly?: boolean;
};

const CROSS_BLOCK_HINT = 'Selection must stay within one block.';
const EMPTY_STATE_COPY = 'Highlight text to comment';

/**
 * REQ-M6-022: toolbar height constant (px). Mirrored in the report column's
 * `pb-20 lg:pb-0` (Tailwind 5rem = 80px) so the always-visible toolbar
 * never covers the last paragraph below `lg`.
 */
export const MOBILE_TOOLBAR_HEIGHT_PX = 72;

export function CommentSelectionToolbar({
    selection,
    containerRef,
    onComment,
    onSuggest,
    onClose,
    readOnly = false,
}: Props) {
    const [, copy] = useClipboard();

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

    const crossesBlocks = selection !== null && selection.blockId === null;
    const hasUsableSelection = selection !== null && selection.blockId !== null;
    const disableWriteActions = !hasUsableSelection || readOnly;
    const disableCopy = selection === null;

    // REQ-M6-022: at click time, re-read the live selection from the DOM
    // rather than trusting the React-state SelectionInfo. The OS bubble
    // has finalised the selection by the time the user taps a button —
    // no timing race.
    const captureForAction = (): SelectionInfo | null => {
        const fresh = captureSelection(containerRef.current);

        if (fresh && fresh.blockId !== null) {
            return fresh;
        }

        // Fall back to the reactive state if a fresh read produced
        // nothing usable (e.g. in test environments without a real
        // Selection API). The disabled state still blocks bogus invocations.
        return hasUsableSelection ? selection : null;
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

        await copy(info.quote);
        onClose();
    };

    return (
        <div
            role="toolbar"
            aria-label="Selection actions"
            data-testid="comment-selection-toolbar"
            data-state={hasUsableSelection ? 'active' : 'empty'}
            data-cross-block={crossesBlocks ? 'true' : 'false'}
            className={cn(
                'fixed inset-x-0 bottom-0 z-50 border-t border-border bg-popover text-popover-foreground shadow-lg',
                'pb-[env(safe-area-inset-bottom)] transition-transform duration-200 ease-out',
                // REQ-M6-022: always visible below `lg`. The container is
                // pinned at translate-y-0; we no longer slide off-screen
                // when the selection clears (which was racy on touch).
                'translate-y-0',
            )}
            style={{ minHeight: MOBILE_TOOLBAR_HEIGHT_PX }}
        >
            {!hasUsableSelection ? (
                <EmptyState crossesBlocks={crossesBlocks} />
            ) : null}

            <div className="mx-auto flex max-w-3xl items-stretch justify-around gap-1 px-2 py-2">
                <ToolbarButton
                    label="Comment"
                    icon={<MessageSquare className="size-5" aria-hidden />}
                    disabled={disableWriteActions}
                    disabledHint={crossesBlocks ? CROSS_BLOCK_HINT : EMPTY_STATE_COPY}
                    onClick={handleComment}
                    testId="comment-selection-toolbar-comment"
                />

                <ToolbarButton
                    label="Suggest edit"
                    icon={<PenLine className="size-5" aria-hidden />}
                    disabled={disableWriteActions}
                    disabledHint={crossesBlocks ? CROSS_BLOCK_HINT : EMPTY_STATE_COPY}
                    onClick={handleSuggest}
                    testId="comment-selection-toolbar-suggest"
                />

                <ToolbarButton
                    label="Copy"
                    icon={<Copy className="size-5" aria-hidden />}
                    disabled={disableCopy}
                    disabledHint={EMPTY_STATE_COPY}
                    onClick={handleCopy}
                    testId="comment-selection-toolbar-copy"
                />
            </div>
        </div>
    );
}

function EmptyState({ crossesBlocks }: { crossesBlocks: boolean }) {
    return (
        <div
            data-testid="comment-selection-toolbar-empty"
            aria-hidden
            className="pointer-events-none absolute inset-x-0 top-0 flex h-12 items-center justify-center gap-2 px-4 text-xs text-muted-foreground"
        >
            <MousePointerSquareDashed className="size-4" aria-hidden />
            <span>
                {crossesBlocks ? CROSS_BLOCK_HINT : EMPTY_STATE_COPY}
            </span>
        </div>
    );
}

type ToolbarButtonProps = {
    label: string;
    icon: React.ReactNode;
    disabled: boolean;
    disabledHint?: string;
    onClick: () => void;
    testId: string;
};

function ToolbarButton({ label, icon, disabled, disabledHint, onClick, testId }: ToolbarButtonProps) {
    return (
        <button
            type="button"
            disabled={disabled}
            data-testid={testId}
            aria-disabled={disabled}
            aria-label={label}
            title={disabled && disabledHint ? disabledHint : label}
            onClick={onClick}
            className={cn(
                'inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-md px-3 text-base font-medium transition-colors',
                disabled
                    ? 'cursor-not-allowed text-muted-foreground opacity-50'
                    : 'hover:bg-accent hover:text-accent-foreground active:bg-accent/80',
            )}
        >
            {icon}
            <span>{label}</span>
        </button>
    );
}

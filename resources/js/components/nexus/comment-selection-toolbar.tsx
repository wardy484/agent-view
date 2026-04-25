import { Copy, MessageSquare, PenLine } from 'lucide-react';
import { useEffect } from 'react';

import { useClipboard } from '@/hooks/use-clipboard';
import type { SelectionInfo } from '@/hooks/use-markdown-selection';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-021: mobile counterpart to REQ-M6-013's floating selection menu.
 *
 * On phones (and other viewports below Tailwind's `lg` breakpoint), the
 * floating menu fights iOS Safari's native selection bubble and frequently
 * misses touch `mouseup` events. This component renders a sticky bottom
 * toolbar instead — touch-friendly hit targets, fixed below the selection
 * bubble, with the same three actions (`Comment`, `Suggest edit`, `Copy`)
 * and the same cross-block disable rule.
 *
 * Visibility is driven entirely by `selection`: when non-null the toolbar
 * slides up; when null it slides down (matching `selectionchange` /
 * pointerup-driven hook updates from `useMarkdownSelection`). Escape closes
 * the toolbar by calling `onClose`.
 *
 * The desktop floating menu (above `lg`) is unchanged; `report-view.tsx`
 * gates each renderer with `hidden lg:block` / `lg:hidden` wrappers so
 * exactly one is visible per breakpoint.
 */

type Props = {
    selection: SelectionInfo | null;
    onComment: (selection: SelectionInfo) => void;
    onSuggest: (selection: SelectionInfo) => void;
    onClose: () => void;
    /** REQ-M6-015 parity: disables Comment + Suggest in historical view. */
    readOnly?: boolean;
};

const CROSS_BLOCK_HINT = 'Selection must stay within one block.';

export function CommentSelectionToolbar({
    selection,
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

    const open = selection !== null;
    const crossesBlocks = selection?.blockId === null;
    const disableWriteActions = !selection || crossesBlocks || readOnly;

    const handleCopy = async () => {
        if (!selection) {
            return;
        }

        await copy(selection.quote);
        onClose();
    };

    return (
        <div
            role="toolbar"
            aria-label="Selection actions"
            aria-hidden={!open}
            data-testid="comment-selection-toolbar"
            data-state={open ? 'open' : 'closed'}
            data-cross-block={crossesBlocks ? 'true' : 'false'}
            className={cn(
                'fixed inset-x-0 bottom-0 z-50 border-t border-border bg-popover text-popover-foreground shadow-lg',
                'pb-[env(safe-area-inset-bottom)] transition-transform duration-200 ease-out',
                open ? 'translate-y-0' : 'pointer-events-none translate-y-full',
            )}
        >
            <div className="mx-auto flex max-w-3xl items-stretch justify-around gap-1 px-2 py-2">
                <ToolbarButton
                    label="Comment"
                    icon={<MessageSquare className="size-5" aria-hidden />}
                    disabled={disableWriteActions}
                    disabledHint={CROSS_BLOCK_HINT}
                    onClick={() => selection && onComment(selection)}
                    testId="comment-selection-toolbar-comment"
                />

                <ToolbarButton
                    label="Suggest edit"
                    icon={<PenLine className="size-5" aria-hidden />}
                    disabled={disableWriteActions}
                    disabledHint={CROSS_BLOCK_HINT}
                    onClick={() => selection && onSuggest(selection)}
                    testId="comment-selection-toolbar-suggest"
                />

                <ToolbarButton
                    label="Copy"
                    icon={<Copy className="size-5" aria-hidden />}
                    disabled={!selection}
                    onClick={handleCopy}
                    testId="comment-selection-toolbar-copy"
                />
            </div>
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

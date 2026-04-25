import { Copy, MessageSquare, PenLine } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import type { SelectionInfo } from '@/hooks/use-markdown-selection';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-013: floating action menu surfaced above a text selection inside a
 * markdown block. Three actions: `Comment`, `Suggest edit`, `Copy`.
 * `Comment` and `Suggest edit` are disabled when the selection crosses
 * markdown block boundaries (`selection.blockId === null`), because anchors
 * are block-scoped per REQ-M6-003. `Copy` is always enabled.
 *
 * UI-only for now; the `onComment` / `onSuggest` callbacks become POSTs to
 * the comment endpoints in subsequent M6 REQs (REQ-M6-014..017).
 */

type Props = {
    selection: SelectionInfo | null;
    onComment: (selection: SelectionInfo) => void;
    onSuggest: (selection: SelectionInfo) => void;
    onClose: () => void;
};

const MENU_HEIGHT = 40;
const MENU_GAP = 8;
const CROSS_BLOCK_HINT = 'Selection must stay within one block.';

export function CommentSelectionMenu({ selection, onComment, onSuggest, onClose }: Props) {
    const [menuEl, setMenuEl] = useState<HTMLDivElement | null>(null);
    const [, copy] = useClipboard();

    const menuRef = useCallback((node: HTMLDivElement | null) => {
        setMenuEl(node);
    }, []);

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

    if (!selection) {
        return null;
    }

    const { rect, blockId, quote } = selection;
    const crossesBlocks = blockId === null;

    const top = rect.top + window.scrollY - MENU_HEIGHT - MENU_GAP;
    // `rect.left` is the left edge of the selection bounding box; centre the
    // menu over the midpoint by subtracting half the menu's measured width
    // (fallback to a sensible default while it lays out for the first time).
    const measured = menuEl?.offsetWidth ?? 220;
    const left = rect.left + window.scrollX + rect.width / 2 - measured / 2;

    const handleCopy = async () => {
        await copy(quote);
        onClose();
    };

    return (
        <TooltipProvider>
            <div
                ref={menuRef}
                role="toolbar"
                aria-label="Selection actions"
                tabIndex={-1}
                data-testid="comment-selection-menu"
                data-cross-block={crossesBlocks ? 'true' : 'false'}
                className={cn(
                    'absolute z-50 flex items-center gap-1 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md',
                )}
                style={{ top, left }}
            >
                <ActionButton
                    label="Comment"
                    icon={<MessageSquare className="size-4" aria-hidden />}
                    disabled={crossesBlocks}
                    disabledHint={CROSS_BLOCK_HINT}
                    onClick={() => onComment(selection)}
                    testId="comment-selection-menu-comment"
                />

                <ActionButton
                    label="Suggest edit"
                    icon={<PenLine className="size-4" aria-hidden />}
                    disabled={crossesBlocks}
                    disabledHint={CROSS_BLOCK_HINT}
                    onClick={() => onSuggest(selection)}
                    testId="comment-selection-menu-suggest"
                />

                <ActionButton
                    label="Copy"
                    icon={<Copy className="size-4" aria-hidden />}
                    disabled={false}
                    onClick={handleCopy}
                    testId="comment-selection-menu-copy"
                />
            </div>
        </TooltipProvider>
    );
}

type ActionButtonProps = {
    label: string;
    icon: React.ReactNode;
    disabled: boolean;
    disabledHint?: string;
    onClick: () => void;
    testId: string;
};

function ActionButton({ label, icon, disabled, disabledHint, onClick, testId }: ActionButtonProps) {
    const button = (
        <button
            type="button"
            disabled={disabled}
            data-testid={testId}
            aria-disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex items-center gap-1.5 rounded px-2 py-1 text-xs font-medium transition-colors',
                disabled
                    ? 'cursor-not-allowed text-muted-foreground opacity-50'
                    : 'hover:bg-accent hover:text-accent-foreground',
            )}
        >
            {icon}
            <span>{label}</span>
        </button>
    );

    if (disabled && disabledHint) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    {/* span wrapper so the tooltip still triggers on a disabled button */}
                    <span className="inline-flex">{button}</span>
                </TooltipTrigger>
                <TooltipContent side="top">{disabledHint}</TooltipContent>
            </Tooltip>
        );
    }

    return button;
}

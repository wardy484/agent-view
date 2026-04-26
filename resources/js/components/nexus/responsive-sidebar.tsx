import { MessageCircle } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-020: responsive presentation wrapper for the snapshot sidebar.
 *
 * Renders a "Comments" trigger button (with an open-count badge) that
 * opens the supplied sidebar `children` inside a right-anchored slide-in
 * `<Sheet>`. The wrapper is itself `lg:hidden` so desktop never sees the
 * trigger; the desktop inline column is rendered separately by the
 * caller (see report-view.tsx) with `hidden lg:flex` so the sidebar
 * children render on the right of the report at and above `lg`.
 *
 * The sheet's `<SheetContent>` uses `forceMount` so the inner
 * `<SnapshotSidebar>` instance keeps its tab state, composer draft, and
 * optimistic mutation queue across open/close cycles.
 */

type Props = {
    children: ReactNode;
    /** Drives the trigger's count badge. Hidden when zero. */
    openCommentCount: number;
};

export function ResponsiveSidebar({ children, openCommentCount }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <div
            data-testid="snapshot-sidebar-mobile-trigger-row"
            className="flex items-center justify-end px-4 py-2 lg:hidden"
        >
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetTrigger asChild>
                    <button
                        type="button"
                        data-testid="snapshot-sidebar-mobile-trigger"
                        className={cn(
                            'inline-flex items-center gap-2 rounded-md border border-border bg-background px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <MessageCircle className="size-4" aria-hidden />
                        <span>Comments</span>
                        {openCommentCount > 0 ? (
                            <span
                                data-testid="snapshot-sidebar-mobile-trigger-badge"
                                className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary/10 px-1.5 text-xs font-semibold text-primary"
                            >
                                {openCommentCount}
                            </span>
                        ) : null}
                    </button>
                </SheetTrigger>
                <SheetContent
                    side="right"
                    forceMount
                    data-testid="snapshot-sidebar-mobile-sheet"
                    className="flex w-full flex-col p-0 sm:max-w-md"
                >
                    <div
                        data-testid="snapshot-sidebar-mobile-sheet-body"
                        className="flex h-full flex-col overflow-hidden"
                    >
                        {children}
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}

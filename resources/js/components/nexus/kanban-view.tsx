import { useMemo } from 'react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type KanbanColumn = {
    key: string;
    label?: string;
};

export type KanbanCardStatus = 'ok' | 'warn' | 'error';

export type KanbanCard = {
    column_key: string;
    title: string;
    body?: string;
    id?: string | number;
    /** REQ-M7-006: optional URL — when present, the card title becomes an external link. */
    link_url?: string;
    /** REQ-M7-006: optional status — drives a coloured 4px left border stripe. */
    status?: KanbanCardStatus;
    /** REQ-M7-006: optional assignee — rendered as a chip in the card footer. */
    assignee?: string;
};

export type KanbanViewPayload = {
    columns: KanbanColumn[];
    cards: KanbanCard[];
};

type Props = {
    payload: KanbanViewPayload;
    className?: string;
    /** When true, the board fills the available viewport (preview / fullscreen mode). */
    fullBleed?: boolean;
};

/**
 * REQ-M7-006: map a card status to its left border stripe utility class.
 * Returns an empty string when no status is set so back-compat is preserved.
 */
function statusStripeClass(status: KanbanCardStatus | undefined): string {
    switch (status) {
        case 'ok':
            return 'border-l-4 border-l-emerald-500';
        case 'warn':
            return 'border-l-4 border-l-amber-500';
        case 'error':
            return 'border-l-4 border-l-red-500';
        default:
            return '';
    }
}

/**
 * REQ-M2-002: Kanban view renders columns and cards from `data_payload`.
 *
 * Pure presentational component — receives the validated payload and renders
 * one column per `columns[]` entry, bucketing cards by `column_key`.
 *
 * REQ-M7-006: cards may carry optional `link_url`, `status`, and `assignee`
 * fields. When present they upgrade the title to an external link, add a
 * coloured 4px left border stripe, and render an assignee chip in the
 * footer. Cards that omit these fields render exactly as before.
 */
export function KanbanView({ payload, className, fullBleed = false }: Props) {
    const columns = useMemo(() => payload?.columns ?? [], [payload?.columns]);
    const cards = useMemo(() => payload?.cards ?? [], [payload?.cards]);

    const cardsByColumn = useMemo(() => {
        const buckets: Record<string, KanbanCard[]> = {};

        for (const card of cards) {
            if (!buckets[card.column_key]) {
                buckets[card.column_key] = [];
            }

            buckets[card.column_key].push(card);
        }

        return buckets;
    }, [cards]);

    if (columns.length === 0) {
        return (
            <div
                data-testid="nexus-kanban-view"
                className={cn('rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground', className)}
            >
                No columns to display.
            </div>
        );
    }

    return (
        <div
            data-testid="nexus-kanban-view"
            data-column-count={columns.length}
            data-card-count={cards.length}
            data-full-bleed={fullBleed}
            className={cn(
                'flex w-full gap-3 overflow-x-auto pb-2',
                fullBleed && 'h-screen min-h-screen items-stretch p-4',
                className,
            )}
        >
            {columns.map((column) => {
                const columnCards = cardsByColumn[column.key] ?? [];
                const label = column.label ?? column.key;

                return (
                    <section
                        key={column.key}
                        data-column-key={column.key}
                        className="flex min-w-[16rem] flex-1 flex-col gap-2 rounded-lg border border-border bg-muted/30 p-3"
                    >
                        <header className="flex items-center justify-between text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            <span>{label}</span>
                            <span className="rounded-full bg-background px-2 py-0.5 text-[10px] font-semibold text-foreground">
                                {columnCards.length}
                            </span>
                        </header>

                        <div className="flex flex-col gap-2">
                            {columnCards.map((card, index) => {
                                const stripeClass = statusStripeClass(card.status);

                                return (
                                    <article
                                        key={card.id ?? `${column.key}-${index}`}
                                        data-testid="nexus-kanban-card"
                                        data-card-status={card.status ?? undefined}
                                        className={cn(
                                            'rounded-md border border-border bg-background p-3 text-sm shadow-sm',
                                            stripeClass,
                                        )}
                                    >
                                        <h3 className="text-sm font-semibold text-foreground">
                                            {card.link_url ? (
                                                <a
                                                    href={card.link_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    data-testid="nexus-kanban-card-link"
                                                    className="underline-offset-2 hover:underline"
                                                >
                                                    {card.title}
                                                </a>
                                            ) : (
                                                card.title
                                            )}
                                        </h3>
                                        {card.body ? (
                                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{card.body}</p>
                                        ) : null}
                                        {card.assignee ? (
                                            <footer className="mt-2 flex items-center">
                                                <Badge
                                                    variant="secondary"
                                                    data-testid="nexus-kanban-card-assignee"
                                                    className="text-[10px]"
                                                >
                                                    {card.assignee}
                                                </Badge>
                                            </footer>
                                        ) : null}
                                    </article>
                                );
                            })}

                            {columnCards.length === 0 ? (
                                <p className="text-xs italic text-muted-foreground">No cards.</p>
                            ) : null}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}

import { useMemo } from 'react';

import { cn } from '@/lib/utils';

export type KanbanColumn = {
    key: string;
    label?: string;
};

export type KanbanCard = {
    column_key: string;
    title: string;
    body?: string;
    id?: string | number;
};

export type KanbanViewPayload = {
    columns: KanbanColumn[];
    cards: KanbanCard[];
};

type Props = {
    payload: KanbanViewPayload;
    className?: string;
};

/**
 * REQ-M2-002: Kanban view renders columns and cards from `data_payload`.
 *
 * Pure presentational component — receives the validated payload and renders
 * one column per `columns[]` entry, bucketing cards by `column_key`.
 */
export function KanbanView({ payload, className }: Props) {
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
            className={cn('flex w-full gap-3 overflow-x-auto pb-2', className)}
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
                            {columnCards.map((card, index) => (
                                <article
                                    key={card.id ?? `${column.key}-${index}`}
                                    data-testid="nexus-kanban-card"
                                    className="rounded-md border border-border bg-background p-3 text-sm shadow-sm"
                                >
                                    <h3 className="text-sm font-semibold text-foreground">{card.title}</h3>
                                    {card.body ? (
                                        <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{card.body}</p>
                                    ) : null}
                                </article>
                            ))}

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

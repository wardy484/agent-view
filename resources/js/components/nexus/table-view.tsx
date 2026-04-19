import { cn } from '@/lib/utils';

export type TableColumn = {
    key: string;
    label?: string;
};

export type TableRow = Record<string, unknown>;

export type TableViewPayload = {
    columns: TableColumn[];
    rows: TableRow[];
};

type Props = {
    payload: TableViewPayload;
    className?: string;
};

/**
 * REQ-M1-005: renders every row in `data_payload.rows` using columns from
 * `data_payload.columns`. Filtering/sort/pagination are TanStack-driven and
 * arrive in REQ-M1-006.
 */
export function TableView({ payload, className }: Props) {
    const columns = payload?.columns ?? [];
    const rows = payload?.rows ?? [];

    return (
        <div
            data-testid="nexus-table-view"
            data-row-count={rows.length}
            className={cn('w-full overflow-auto rounded-lg border border-border', className)}
        >
            <table className="w-full text-left text-sm">
                <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} scope="col" className="px-4 py-2 font-medium">
                                {column.label ?? column.key}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, rowIndex) => (
                        <tr
                            key={rowIndex}
                            data-row-index={rowIndex}
                            className="border-t border-border/60 last:border-b-0 hover:bg-muted/30"
                        >
                            {columns.map((column) => (
                                <td key={column.key} className="px-4 py-2 align-top">
                                    {formatCell(row[column.key])}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function formatCell(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    try {
        return JSON.stringify(value);
    } catch {
        return '';
    }
}

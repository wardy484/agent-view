import { useMemo, useState } from 'react';
import {
    type ColumnDef,
    type ColumnFiltersState,
    type SortingState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

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
    /** Initial page size; user can change this via the page-size select. */
    initialPageSize?: number;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

/**
 * REQ-M1-005: renders every row in `data_payload.rows` using columns from
 * `data_payload.columns`.
 *
 * REQ-M1-006: filter, multi-column sort, and pagination are powered entirely
 * by TanStack Table on the client — zero network calls.
 */
export function TableView({ payload, className, initialPageSize = 25 }: Props) {
    const columns = payload?.columns ?? [];
    const rows = payload?.rows ?? [];
    const [globalFilter, setGlobalFilter] = useState('');
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);

    const tableColumns = useMemo<ColumnDef<TableRow>[]>(
        () =>
            columns.map((column) => ({
                id: column.key,
                accessorFn: (row) => row[column.key],
                header: column.label ?? column.key,
                cell: (info) => formatCell(info.getValue()),
            })),
        [columns],
    );

    const table = useReactTable({
        data: rows,
        columns: tableColumns,
        state: { globalFilter, sorting, columnFilters },
        onGlobalFilterChange: setGlobalFilter,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        enableMultiSort: true,
        initialState: { pagination: { pageSize: initialPageSize } },
        globalFilterFn: 'includesString',
    });

    const filteredCount = table.getFilteredRowModel().rows.length;
    const pageRows = table.getRowModel().rows;
    const pageIndex = table.getState().pagination.pageIndex;
    const pageSize = table.getState().pagination.pageSize;

    return (
        <div
            data-testid="nexus-table-view"
            data-row-count={rows.length}
            className={cn('flex w-full flex-col gap-3', className)}
        >
            <div className="flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    value={globalFilter}
                    onChange={(event) => setGlobalFilter(event.target.value)}
                    placeholder="Filter rows…"
                    className="w-64 rounded-md border border-border bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    data-testid="nexus-table-filter"
                />
                <span className="text-xs text-muted-foreground">
                    {filteredCount} of {rows.length} rows
                </span>
            </div>

            <div className="w-full overflow-auto rounded-lg border border-border">
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const sortDirection = header.column.getIsSorted();
                                    return (
                                        <th key={header.id} scope="col" className="px-4 py-2 font-medium">
                                            <button
                                                type="button"
                                                onClick={(event) =>
                                                    header.column.getToggleSortingHandler()?.({
                                                        ...event,
                                                        // Shift-click toggles multi-column sort in TanStack;
                                                        // forward the modifier so the user can stack sorts.
                                                        shiftKey: event.shiftKey,
                                                    })
                                                }
                                                className="inline-flex items-center gap-1 hover:text-foreground"
                                            >
                                                {flexRender(header.column.columnDef.header, header.getContext())}
                                                <SortIcon direction={sortDirection} />
                                            </button>
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {pageRows.length === 0 ? (
                            <tr>
                                <td colSpan={tableColumns.length} className="px-4 py-6 text-center text-muted-foreground">
                                    No rows match the current filter.
                                </td>
                            </tr>
                        ) : (
                            pageRows.map((row) => (
                                <tr
                                    key={row.id}
                                    data-row-index={row.index}
                                    className="border-t border-border/60 last:border-b-0 hover:bg-muted/30"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <td key={cell.id} className="px-4 py-2 align-top">
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <div className="flex items-center gap-2">
                    <label htmlFor="nexus-table-page-size">Rows per page</label>
                    <select
                        id="nexus-table-page-size"
                        value={pageSize}
                        onChange={(event) => table.setPageSize(Number(event.target.value))}
                        className="rounded-md border border-border bg-background px-2 py-1 text-foreground"
                    >
                        {PAGE_SIZE_OPTIONS.map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <span>
                        Page {pageIndex + 1} of {Math.max(table.getPageCount(), 1)}
                    </span>
                    <button
                        type="button"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                        className="rounded-md border border-border px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                        className="rounded-md border border-border px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    );
}

function SortIcon({ direction }: { direction: false | 'asc' | 'desc' }) {
    if (direction === 'asc') {
        return <ArrowUp className="size-3" aria-hidden />;
    }

    if (direction === 'desc') {
        return <ArrowDown className="size-3" aria-hidden />;
    }

    return <ArrowUpDown className="size-3 opacity-40" aria-hidden />;
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

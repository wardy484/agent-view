import { rankItem } from '@tanstack/match-sorter-utils';
import {
    
    
    
    
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable
} from '@tanstack/react-table';
import type {ColumnDef, ColumnFiltersState, FilterFn, SortingState} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

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
    /** When true, the table fills the available viewport (preview / fullscreen mode). */
    fullBleed?: boolean;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

/**
 * REQ-M2-007: fuzzy-match across every column's stringified cell value.
 *
 * `@tanstack/match-sorter-utils.rankItem` scores each row by cell similarity;
 * a row matches when any cell ranks above the threshold. No network calls,
 * no server-side filtering.
 */
const fuzzyFilter: FilterFn<TableRow> = (row, _columnId, filterValue) => {
    if (typeof filterValue !== 'string' || filterValue === '') {
        return true;
    }

    // Score every visible column value; a row matches if ANY cell passes.
    for (const cell of row.getAllCells()) {
        const value = cell.getValue();
        const stringified = cellToString(value);

        if (rankItem(stringified, filterValue).passed) {
            return true;
        }
    }

    return false;
};

function cellToString(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    try {
        return JSON.stringify(value);
    } catch {
        return '';
    }
}

/**
 * REQ-M2-009: read/write the table's filter + sort state to the URL so views
 * are shareable. We use the browser History API directly (no Inertia visit)
 * to stay purely client-side — REQ-M2-007 and REQ-M2-008 both require "no
 * network calls."
 *
 * Query keys:
 *  - `q`       — global fuzzy filter
 *  - `f.<key>` — per-column filter (e.g. `f.role=engineer`)
 *  - `sort`    — comma-delimited `key:dir` pairs (e.g. `sort=role:asc,name:desc`)
 */
const URL_QUERY_KEY = 'q';
const URL_COLUMN_FILTER_PREFIX = 'f.';
const URL_SORT_KEY = 'sort';

function readInitialStateFromUrl(): {
    globalFilter: string;
    columnFilters: ColumnFiltersState;
    sorting: SortingState;
} {
    if (typeof window === 'undefined') {
        return { globalFilter: '', columnFilters: [], sorting: [] };
    }

    const params = new URLSearchParams(window.location.search);

    const globalFilter = params.get(URL_QUERY_KEY) ?? '';

    const columnFilters: ColumnFiltersState = [];

    for (const [key, value] of params.entries()) {
        if (key.startsWith(URL_COLUMN_FILTER_PREFIX) && value !== '') {
            columnFilters.push({ id: key.slice(URL_COLUMN_FILTER_PREFIX.length), value });
        }
    }

    const sorting: SortingState = [];
    const sortParam = params.get(URL_SORT_KEY);

    if (sortParam) {
        for (const chunk of sortParam.split(',')) {
            const [id, dir] = chunk.split(':');

            if (id && (dir === 'asc' || dir === 'desc')) {
                sorting.push({ id, desc: dir === 'desc' });
            }
        }
    }

    return { globalFilter, columnFilters, sorting };
}

function writeStateToUrl(
    globalFilter: string,
    columnFilters: ColumnFiltersState,
    sorting: SortingState,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);

    // Clear any previous table-owned params before re-writing.
    params.delete(URL_QUERY_KEY);
    params.delete(URL_SORT_KEY);

    for (const key of Array.from(params.keys())) {
        if (key.startsWith(URL_COLUMN_FILTER_PREFIX)) {
            params.delete(key);
        }
    }

    if (globalFilter !== '') {
        params.set(URL_QUERY_KEY, globalFilter);
    }

    for (const filter of columnFilters) {
        if (typeof filter.value === 'string' && filter.value !== '') {
            params.set(`${URL_COLUMN_FILTER_PREFIX}${filter.id}`, filter.value);
        }
    }

    if (sorting.length > 0) {
        params.set(
            URL_SORT_KEY,
            sorting.map((s) => `${s.id}:${s.desc ? 'desc' : 'asc'}`).join(','),
        );
    }

    const query = params.toString();
    const next = `${window.location.pathname}${query === '' ? '' : `?${query}`}${window.location.hash}`;
    window.history.replaceState(window.history.state, '', next);
}

/**
 * REQ-M1-005: renders every row in `data_payload.rows` using columns from
 * `data_payload.columns`.
 *
 * REQ-M1-006: filter, multi-column sort, and pagination are powered entirely
 * by TanStack Table on the client — zero network calls.
 *
 * REQ-M2-007: global filter does fuzzy matching across every column.
 * REQ-M2-008: shift-click on a column header stacks secondary/tertiary sorts.
 * REQ-M2-009: filter + sort state is mirrored into the URL so the view is
 *             shareable — refreshing or sharing the link restores the exact
 *             filter/sort configuration.
 */
export function TableView({ payload, className, initialPageSize = 25, fullBleed = false }: Props) {
    const columns = useMemo(() => payload?.columns ?? [], [payload?.columns]);
    const rows = useMemo(() => payload?.rows ?? [], [payload?.rows]);

    const initial = useMemo(() => readInitialStateFromUrl(), []);
    const [globalFilter, setGlobalFilter] = useState<string>(initial.globalFilter);
    const [sorting, setSorting] = useState<SortingState>(initial.sorting);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(initial.columnFilters);

    // Skip the first sync so we don't overwrite the URL we just read from.
    const firstRenderRef = useRef(true);
    useEffect(() => {
        if (firstRenderRef.current) {
            firstRenderRef.current = false;

            return;
        }

        writeStateToUrl(globalFilter, columnFilters, sorting);
    }, [globalFilter, columnFilters, sorting]);

    const tableColumns = useMemo<ColumnDef<TableRow>[]>(
        () =>
            columns.map((column) => ({
                id: column.key,
                accessorFn: (row) => row[column.key],
                header: column.label ?? column.key,
                cell: (info) => formatCell(info.getValue()),
                filterFn: 'includesString',
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
        isMultiSortEvent: (event) => (event as unknown as { shiftKey?: boolean }).shiftKey === true,
        initialState: { pagination: { pageSize: initialPageSize } },
        globalFilterFn: fuzzyFilter,
        filterFns: { fuzzy: fuzzyFilter },
    });

    const filteredCount = table.getFilteredRowModel().rows.length;
    const pageRows = table.getRowModel().rows;
    const pageIndex = table.getState().pagination.pageIndex;
    const pageSize = table.getState().pagination.pageSize;

    return (
        <div
            data-testid="nexus-table-view"
            data-row-count={rows.length}
            data-full-bleed={fullBleed}
            className={cn(
                'flex w-full flex-col gap-3',
                fullBleed && 'h-screen min-h-screen gap-2 px-4 py-3',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    value={globalFilter}
                    onChange={(event) => setGlobalFilter(event.target.value)}
                    placeholder="Fuzzy-search rows…"
                    className="w-64 rounded-md border border-border bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    data-testid="nexus-table-filter"
                />
                <span className="text-xs text-muted-foreground">
                    {filteredCount} of {rows.length} rows
                </span>
            </div>

            <div
                className={cn(
                    'w-full overflow-auto rounded-lg border border-border',
                    fullBleed && 'flex-1',
                )}
            >
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const sortDirection = header.column.getIsSorted();
                                    const sortIndex = header.column.getSortIndex();

                                    return (
                                        <th key={header.id} scope="col" className="px-4 py-2 align-top font-medium">
                                            <div className="flex flex-col gap-1">
                                                <button
                                                    type="button"
                                                    onClick={(event) =>
                                                        header.column.getToggleSortingHandler()?.({
                                                            ...event,
                                                            // REQ-M2-008: forward shiftKey so TanStack stacks sorts.
                                                            shiftKey: event.shiftKey,
                                                        })
                                                    }
                                                    title="Click to sort. Shift-click to add a secondary sort."
                                                    className="inline-flex items-center gap-1 hover:text-foreground"
                                                >
                                                    {flexRender(header.column.columnDef.header, header.getContext())}
                                                    <SortIcon direction={sortDirection} />
                                                    {sortDirection && sorting.length > 1 ? (
                                                        <span className="ml-1 rounded bg-muted px-1 text-[9px] font-semibold text-muted-foreground">
                                                            {sortIndex + 1}
                                                        </span>
                                                    ) : null}
                                                </button>
                                                <input
                                                    type="search"
                                                    value={(header.column.getFilterValue() as string | undefined) ?? ''}
                                                    onChange={(event) =>
                                                        header.column.setFilterValue(event.target.value || undefined)
                                                    }
                                                    placeholder="Filter…"
                                                    data-testid={`nexus-table-column-filter-${header.column.id}`}
                                                    className="w-full rounded-md border border-border bg-background px-2 py-1 text-xs normal-case tracking-normal text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                />
                                            </div>
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

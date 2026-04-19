<?php

declare(strict_types=1);

it('REQ-M1-006: TableView wires TanStack Table client-side filter/sort/pagination', function (): void {
    // REQ-M1-006: filter, multi-column sort, and pagination must run
    // client-side via TanStack Table — zero network calls. We assert at the
    // source level that the renderer imports the row-model factories that
    // power these features and that they're attached to useReactTable.
    $source = file_get_contents(resource_path('js/components/nexus/table-view.tsx'));

    expect($source)
        ->toContain("from '@tanstack/react-table'")
        ->toContain('useReactTable')
        ->toContain('getCoreRowModel')
        ->toContain('getFilteredRowModel')
        ->toContain('getSortedRowModel')
        ->toContain('getPaginationRowModel');

    // Multi-column sort is opt-in via TanStack: the table must enable it
    // explicitly (single-column sort is the default).
    expect($source)->toContain('enableMultiSort');

    // Belt-and-braces: no network calls for filter/sort/pagination.
    expect($source)
        ->not->toContain('router.visit')
        ->not->toContain('router.get')
        ->not->toContain('useHttp')
        ->not->toContain('fetch(');
});

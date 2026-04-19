<?php

declare(strict_types=1);

/**
 * Source-level tests for the three M2 table enhancements. The TanStack Table
 * client-side behaviour is covered by the library; these tests pin the
 * wiring so the renderer can't silently regress.
 */
it('REQ-M2-007: global filter fuzz-matches across every column with zero network calls', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/table-view.tsx'));

    // Uses @tanstack/match-sorter-utils for fuzzy ranking.
    expect($source)
        ->toContain("from '@tanstack/match-sorter-utils'")
        ->toContain('rankItem(')
        ->toContain('globalFilterFn: fuzzyFilter')
        // The custom filter walks every cell in a row, not a single column.
        ->toContain('row.getAllCells()')
        // No server round-trip for filtering.
        ->not->toContain('router.visit')
        ->not->toContain('router.get')
        ->not->toContain('useHttp')
        ->not->toContain('fetch(');
});

it('REQ-M2-008: shift-click stacks secondary/tertiary sort keys via TanStack multi-sort', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/table-view.tsx'));

    expect($source)
        ->toContain('enableMultiSort: true')
        // Shift-click is the TanStack idiom; we must forward the modifier.
        ->toContain('shiftKey: event.shiftKey')
        // Explicit multi-sort trigger predicate wired for clarity.
        ->toContain('isMultiSortEvent:')
        // No network calls for sorting.
        ->not->toContain('router.visit')
        ->not->toContain('router.get');
});

it('REQ-M2-009: filter and sort state is persisted to the URL via history.replaceState', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/table-view.tsx'));

    expect($source)
        ->toContain('readInitialStateFromUrl')
        ->toContain('writeStateToUrl')
        ->toContain('URLSearchParams')
        ->toContain('window.history.replaceState')
        // The three URL keys that make views shareable.
        ->toContain("'q'")
        ->toContain("'sort'")
        ->toContain("'f.'")
        // Full page round-trip would defeat "without a network call".
        ->not->toContain('router.visit')
        ->not->toContain('router.get');
});

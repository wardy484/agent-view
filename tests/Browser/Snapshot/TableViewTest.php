<?php

declare(strict_types=1);

use App\Models\User;

it('REQ-M11-009: renders the seeded ui-baseline-table snapshot and re-orders rows on header click', function (): void {
    $user = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();

    $this->actingAs($user);

    $page = visit('/workbenches/ui-baseline/snapshots/ui-baseline-table');

    // Column headers and seeded rows are visible.
    $page->assertSee('ID')
        ->assertSee('Name')
        ->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertNoJavaScriptErrors();

    // Capture the initial first-row name (column 2 of the first tbody row).
    $firstRowSelector = '[data-testid="nexus-table-view"] tbody tr td:nth-child(2)';

    $initialFirstRowName = trim((string) $page->script(
        "document.querySelector('{$firstRowSelector}').textContent"
    )[0]);

    expect($initialFirstRowName)->not->toBe('');

    // Click the sortable "Name" column header (rendered inside a <button> in the <th>).
    // Toggle twice to guarantee a descending sort, which flips the row order.
    $page->click('Name')
        ->assertNoJavaScriptErrors()
        ->click('Name')
        ->assertNoJavaScriptErrors();

    $sortedFirstRowName = trim((string) $page->script(
        "document.querySelector('{$firstRowSelector}').textContent"
    )[0]);

    expect($sortedFirstRowName)
        ->not->toBe('')
        ->and($sortedFirstRowName)->not->toBe($initialFirstRowName);
});

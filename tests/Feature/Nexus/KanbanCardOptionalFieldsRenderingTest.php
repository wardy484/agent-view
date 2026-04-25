<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * REQ-M7-006 — `resources/js/components/nexus/kanban-view.tsx` renders the new
 * card fields:
 *  - `link_url` makes the card title an `<a target="_blank" rel="noopener">`,
 *  - `status` drives a 4 px left border stripe in the corresponding colour
 *    (green / amber / red for `ok` / `warn` / `error`),
 *  - `assignee` renders as a small chip in the card footer.
 *
 * Cards without the optional fields render exactly as before. Keyboard
 * navigation, drag-and-drop, and selection semantics are unchanged.
 *
 * The component is exercised via Inertia and source-level assertions —
 * matching the patterns used by the rest of `tests/Feature/Nexus/`. The
 * project does not currently host Pest browser tests, so we lean on source
 * inspection plus an Inertia round-trip to confirm the new payload reaches
 * the page exactly as authored.
 */
it('REQ-M7-006: renders link_url as a target=_blank anchor on the card title', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/kanban-view.tsx'));

    expect($source)
        ->toContain('card.link_url')
        ->toContain('href={card.link_url}')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"');
});

it('REQ-M7-006: applies status border stripe colour for ok|warn|error', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/kanban-view.tsx'));

    expect($source)
        ->toContain("'border-l-4 border-l-emerald-500'")
        ->toContain("'border-l-4 border-l-amber-500'")
        ->toContain("'border-l-4 border-l-red-500'")
        ->toContain('statusStripeClass');
});

it('REQ-M7-006: renders assignee as a chip in the card footer', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/kanban-view.tsx'));

    expect($source)
        ->toContain('card.assignee')
        ->toContain("from '@/components/ui/badge'")
        ->toContain('<Badge')
        ->toContain('data-testid="nexus-kanban-card-assignee"');
});

it('REQ-M7-006: cards without optional fields render unchanged', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/kanban-view.tsx'));

    // Title must remain plain text when link_url is absent — the link branch
    // is gated on `card.link_url ?` and the `else` branch returns the bare
    // `card.title` value, not an anchor wrapper.
    expect($source)
        ->toContain('card.link_url ? (')
        ->toContain(': (')
        // The assignee footer is gated on `card.assignee ?` — without an
        // assignee, no <footer> / <Badge> is emitted at all.
        ->toContain('card.assignee ? (')
        // Stripe class must be empty (no `border-l-*`) when status is unset;
        // the helper returns '' in the default branch.
        ->toContain("default:\n            return '';");
});

it('REQ-M7-006: snapshot page round-trips the new optional kanban card fields', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-omega']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'release-board']);

    $columns = [
        ['key' => 'todo', 'label' => 'To Do'],
        ['key' => 'doing', 'label' => 'Doing'],
        ['key' => 'done', 'label' => 'Done'],
    ];
    $cards = [
        [
            'column_key' => 'todo',
            'title' => 'Plain card',
        ],
        [
            'column_key' => 'doing',
            'title' => 'Linked card',
            'link_url' => 'https://example.com/issue/42',
            'status' => 'warn',
            'assignee' => 'alex',
        ],
        [
            'column_key' => 'done',
            'title' => 'Shipped',
            'status' => 'ok',
            'assignee' => 'sam',
        ],
    ];

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'kanban',
        dataPayload: ['columns' => $columns, 'cards' => $cards],
    );

    $snapshot->forceFill(['current_version_id' => $version->id])->save();

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'kanban')
            ->where('version.data_payload.cards.1.link_url', 'https://example.com/issue/42')
            ->where('version.data_payload.cards.1.status', 'warn')
            ->where('version.data_payload.cards.1.assignee', 'alex')
            ->where('version.data_payload.cards.2.status', 'ok')
            ->where('version.data_payload.cards.2.assignee', 'sam')
        );
});

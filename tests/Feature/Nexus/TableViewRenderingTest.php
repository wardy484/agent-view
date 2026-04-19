<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M1-005: snapshot page hands every row in data_payload.rows to the table view', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'engineers']);

    $columns = [
        ['key' => 'id', 'label' => 'ID'],
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'role', 'label' => 'Role'],
    ];

    $rows = [
        ['id' => 1, 'name' => 'Ada Lovelace', 'role' => 'Mathematician'],
        ['id' => 2, 'name' => 'Linus Torvalds', 'role' => 'Kernel Hacker'],
        ['id' => 3, 'name' => 'Grace Hopper', 'role' => 'Rear Admiral'],
        ['id' => 4, 'name' => 'Margaret Hamilton', 'role' => 'Software Engineer'],
        ['id' => 5, 'name' => 'Dennis Ritchie', 'role' => 'Language Designer'],
    ];

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => $columns, 'rows' => $rows],
    );

    $snapshot->forceFill(['current_version_id' => $version->id])->save();

    $this->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'table')
            ->where('version.data_payload.columns', $columns)
            ->where('version.data_payload.rows', $rows)
            ->where('version.data_payload.rows', fn ($delivered) => count($delivered) === count($rows))
        );
});

it('REQ-M1-005: TableView feeds every payload row into TanStack', function (): void {
    // Belt-and-braces: ensure the React renderer hands the full `rows` array
    // to TanStack (`data: rows`) — pagination/filtering then operate on the
    // full set client-side per REQ-M1-006, but the input must be the entire
    // payload, never a sliced/limited subset.
    $source = file_get_contents(resource_path('js/components/nexus/table-view.tsx'));

    expect($source)
        ->toContain('data: rows')
        ->toContain('columns.map(')
        ->not->toContain('rows.slice(')
        ->not->toContain('rows.filter(');
});

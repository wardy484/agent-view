<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M2-002: snapshot page hands the kanban data_payload to the KanbanView', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-beta']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'launch-board']);

    $columns = [
        ['key' => 'backlog', 'label' => 'Backlog'],
        ['key' => 'doing', 'label' => 'In Progress'],
        ['key' => 'done', 'label' => 'Done'],
    ];
    $cards = [
        ['column_key' => 'backlog', 'title' => 'Write spec'],
        ['column_key' => 'doing', 'title' => 'Wire MCP tool'],
        ['column_key' => 'done', 'title' => 'Land M1', 'body' => '14/14 green'],
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
            ->where('version.data_payload.columns', $columns)
            ->where('version.data_payload.cards', $cards)
        );
});

it('REQ-M2-002: snapshot page dispatches kanban view_type to the KanbanView component', function (): void {
    $dispatcher = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($dispatcher)
        ->toContain("'kanban'")
        ->toContain('<KanbanView')
        ->toContain("from '@/components/nexus/kanban-view'");
});

it('REQ-M2-002: KanbanView source renders one section per column and card title', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/kanban-view.tsx'));

    expect($source)
        ->toContain('columns.map(')
        ->toContain('data-column-key={column.key}')
        ->toContain('data-testid="nexus-kanban-card"')
        ->toContain('card.title')
        ->not->toContain('cards.slice(')
        ->not->toContain('router.visit')
        ->not->toContain('router.get')
        ->not->toContain('useHttp');
});

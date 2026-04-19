<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M2-001: accepts a well-formed kanban payload and persists a snapshot version', function (): void {
    $arguments = [
        'workbench_slug' => 'planning-team',
        'view_type' => 'kanban',
        'data_payload' => [
            'columns' => [
                ['key' => 'todo', 'label' => 'To Do'],
                ['key' => 'doing', 'label' => 'In Progress'],
                ['key' => 'done', 'label' => 'Done'],
            ],
            'cards' => [
                ['column_key' => 'todo', 'title' => 'Pick paint colors'],
                ['column_key' => 'doing', 'title' => 'Hang drywall'],
            ],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertOk()
        ->assertSee('/workbenches/planning-team/snapshots/');

    expect(Workbench::query()->where('slug', 'planning-team')->exists())->toBeTrue();
    expect(SnapshotVersion::query()->count())->toBe(1);

    $version = SnapshotVersion::query()->first();
    expect($version->view_type)->toBe('kanban');
    expect($version->preview_html)->not->toBeNull();
    expect($version->preview_html)->toContain('data-nexus-preview="kanban"');
});

it('REQ-M2-001: rejects a kanban payload missing columns and persists nothing', function (): void {
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'planning-team',
        'view_type' => 'kanban',
        'data_payload' => [
            'cards' => [['column_key' => 'todo', 'title' => 'x']],
        ],
    ])->assertHasErrors([
        'data_payload.columns is required and must be a non-empty array.',
    ]);

    expect(Workbench::query()->count())->toBe(0);
    expect(Snapshot::query()->count())->toBe(0);
    expect(SnapshotVersion::query()->count())->toBe(0);
});

it('REQ-M2-001: rejects a kanban card referencing an unknown column', function (): void {
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'planning-team',
        'view_type' => 'kanban',
        'data_payload' => [
            'columns' => [['key' => 'todo']],
            'cards' => [['column_key' => 'ghost', 'title' => 'Lost soul']],
        ],
    ])->assertHasErrors([
        "data_payload.cards[0].column_key 'ghost' does not match any declared column.",
    ]);

    expect(SnapshotVersion::query()->count())->toBe(0);
});

<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M1-008: rejects a table payload missing columns and persists nothing', function (): void {
    $arguments = [
        'workbench_slug' => 'qa-suite',
        'view_type' => 'table',
        'data_payload' => [
            'rows' => [['id' => 1, 'name' => 'NoColumns']],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'data_payload.columns is required and must be a non-empty array.',
        ]);

    expect(Workbench::query()->where('slug', 'qa-suite')->exists())->toBeFalse();
    expect(Snapshot::query()->count())->toBe(0);
    expect(SnapshotVersion::query()->count())->toBe(0);
});

it('REQ-M1-008: rejects a table payload missing rows and persists nothing', function (): void {
    $arguments = [
        'workbench_slug' => 'qa-suite',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'data_payload.rows is required and must be an array.',
        ]);

    expect(SnapshotVersion::query()->count())->toBe(0);
});

it('REQ-M1-008: rejects an empty columns array', function (): void {
    $arguments = [
        'workbench_slug' => 'qa-suite',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [],
            'rows' => [],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'data_payload.columns is required and must be a non-empty array.',
        ]);
});

it('REQ-M1-008: rejects columns entries without a string key', function (): void {
    $arguments = [
        'workbench_slug' => 'qa-suite',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['label' => 'No Key Here']],
            'rows' => [],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'data_payload.columns[0].key is required and must be a string.',
        ]);
});

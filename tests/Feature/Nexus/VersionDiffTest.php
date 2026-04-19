<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Nexus\VersionDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M3-009: VersionDiff::between returns added, removed and changed rows across two table versions', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $columns = [
        ['key' => 'id', 'label' => 'ID'],
        ['key' => 'status', 'label' => 'Status'],
    ];

    $v1 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: [
            'columns' => $columns,
            'rows' => [
                ['id' => 1, 'status' => 'open'],
                ['id' => 2, 'status' => 'closed'],
                ['id' => 3, 'status' => 'open'],
            ],
        ],
    );

    $v2 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: [
            'columns' => $columns,
            'rows' => [
                ['id' => 1, 'status' => 'open'],        // unchanged
                ['id' => 2, 'status' => 'reopened'],    // changed
                // id 3 removed
                ['id' => 4, 'status' => 'open'],        // added
            ],
        ],
    );

    $diff = VersionDiff::between($v1, $v2);

    expect($diff['added'])->toHaveCount(1)
        ->and($diff['added'][0])->toMatchArray(['id' => 4, 'status' => 'open'])
        ->and($diff['removed'])->toHaveCount(1)
        ->and($diff['removed'][0])->toMatchArray(['id' => 3, 'status' => 'open'])
        ->and($diff['changed'])->toHaveCount(1)
        ->and($diff['changed'][0]['key'])->toBe(2)
        ->and($diff['changed'][0]['before'])->toMatchArray(['id' => 2, 'status' => 'closed'])
        ->and($diff['changed'][0]['after'])->toMatchArray(['id' => 2, 'status' => 'reopened']);
});

it('REQ-M3-009: VersionDiff::between handles identical versions with empty diffs', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $payload = [
        'columns' => [['key' => 'id', 'label' => 'ID']],
        'rows' => [['id' => 1], ['id' => 2]],
    ];

    $v1 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: $payload,
    );

    $v2 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: $payload,
    );

    $diff = VersionDiff::between($v1, $v2);

    expect($diff['added'])->toBe([])
        ->and($diff['removed'])->toBe([])
        ->and($diff['changed'])->toBe([]);
});

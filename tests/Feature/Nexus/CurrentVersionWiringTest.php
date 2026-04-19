<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M1-010: first append sets current_version_id to that version', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    expect($snapshot->current_version_id)->toBeNull();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    expect($snapshot->fresh()->current_version_id)->toBe($version->id);
});

it('REQ-M1-010: subsequent appends advance current_version_id', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $payload = ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]];
    $first = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    expect($snapshot->fresh()->current_version_id)->toBe($first->id);

    $second = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    expect($snapshot->fresh()->current_version_id)->toBe($second->id)
        ->and($second->id)->not->toBe($first->id);
});

<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('REQ-M1-003: assigns monotonic revisions starting at 1 for each snapshot', function (): void {
    $snapshot = Snapshot::factory()->create();

    $payload = ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]];

    $first = SnapshotVersioning::append($snapshot, 'table', $payload);
    $second = SnapshotVersioning::append($snapshot, 'table', $payload);
    $third = SnapshotVersioning::append($snapshot, 'table', $payload);

    expect([$first->revision, $second->revision, $third->revision])
        ->toBe([1, 2, 3]);
});

it('REQ-M1-003: revisions are scoped per snapshot — sibling snapshots also start at 1', function (): void {
    $snapshotA = Snapshot::factory()->create();
    $snapshotB = Snapshot::factory()->create();

    $payload = ['columns' => [['key' => 'id']], 'rows' => []];

    SnapshotVersioning::append($snapshotA, 'table', $payload);
    SnapshotVersioning::append($snapshotA, 'table', $payload);
    $bFirst = SnapshotVersioning::append($snapshotB, 'table', $payload);

    expect($bFirst->revision)->toBe(1);
    expect(SnapshotVersion::query()->where('snapshot_id', $snapshotA->id)->max('revision'))->toBe(2);
});

it('REQ-M1-003: rejects duplicate (snapshot_id, revision) at the database level', function (): void {
    $snapshot = Snapshot::factory()->create();

    $payload = ['columns' => [['key' => 'id']], 'rows' => []];
    SnapshotVersioning::append($snapshot, 'table', $payload);

    // Bypass the SnapshotVersioning guard (REQ-M1-009) by writing through the
    // query builder so that the DB-level UNIQUE(snapshot_id, revision) is the
    // assertion under test.
    expect(fn () => DB::table('snapshot_versions')->insert([
        'snapshot_id' => $snapshot->id,
        'revision' => 1,
        'view_type' => 'table',
        'data_payload' => json_encode($payload),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

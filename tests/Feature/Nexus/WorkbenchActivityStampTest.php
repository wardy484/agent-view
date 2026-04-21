<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('REQ-M6-006: SnapshotVersioning::append updates last_activity_at in the same transaction', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    // Rewind last_activity_at so we can detect the update.
    DB::table('workbenches')
        ->where('id', $workbench->id)
        ->update(['last_activity_at' => now()->subYear()]);

    $before = now()->subSecond();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    $workbench->refresh();

    expect($workbench->last_activity_at)->not->toBeNull()
        ->and($workbench->last_activity_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
});

it('REQ-M6-006: SnapshotVersioning::append clears archived_at alongside last_activity_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'archived_at' => now()->subMonth(),
    ]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    expect($workbench->fresh()->archived_at)->toBeNull();
});

it('REQ-M6-006: reading a snapshot does NOT touch last_activity_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    // Pin last_activity_at into the past, then issue GET requests. None of
    // them should bump the column — only appends do.
    $pinned = now()->subHour()->startOfSecond();

    DB::table('workbenches')
        ->where('id', $workbench->id)
        ->update(['last_activity_at' => $pinned]);

    $this->actingAs($owner)
        ->get("/workbenches/{$workbench->slug}/snapshots/{$snapshot->slug}")
        ->assertOk();

    $after = $workbench->fresh()->last_activity_at;

    expect($after)->not->toBeNull()
        ->and($after->timestamp)->toBe($pinned->timestamp);
});

<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M6-004: POST /workbenches/{slug}/archive sets archived_at and clears pinned_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'archived_at' => null,
        'pinned_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$workbench->slug}/archive")
        ->assertOk();

    $workbench->refresh();

    expect($workbench->archived_at)->not->toBeNull()
        ->and($workbench->pinned_at)->toBeNull();
});

it('REQ-M6-004: DELETE /workbenches/{slug}/archive clears archived_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'archived_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->deleteJson("/workbenches/{$workbench->slug}/archive")
        ->assertOk();

    expect($workbench->fresh()->archived_at)->toBeNull();
});

it('REQ-M6-004: MCP writes against an archived workbench auto-unarchive it', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'archived_at' => now()->subWeek(),
    ]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    expect($workbench->fresh()->archived_at)->toBeNull();
});

it('REQ-M6-004: snapshot pages remain readable when the workbench is archived', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'archived_at' => now(),
    ]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    // The append just auto-unarchived it — re-archive for the read check.
    $workbench->forceFill(['archived_at' => now()])->save();

    $this->actingAs($owner)
        ->get("/workbenches/{$workbench->slug}/snapshots/{$snapshot->slug}")
        ->assertOk();
});

it('REQ-M6-004: non-owner archive attempt returns 403', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->postJson("/workbenches/{$workbench->slug}/archive")
        ->assertStatus(403);

    expect($workbench->fresh()->archived_at)->toBeNull();
});

it('REQ-M6-004: unauthenticated archive attempt returns 401', function (): void {
    $workbench = Workbench::factory()->create();

    $this->postJson("/workbenches/{$workbench->slug}/archive")
        ->assertStatus(401);
});

it('REQ-M6-004: unknown slug on archive returns 404', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/workbenches/does-not-exist/archive')
        ->assertStatus(404);
});

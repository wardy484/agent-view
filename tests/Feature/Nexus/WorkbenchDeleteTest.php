<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('REQ-M6-005: DELETE /workbenches/{slug} soft-deletes the workbench', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->deleteJson("/workbenches/{$workbench->slug}")
        ->assertOk();

    // Default queries must NOT return the soft-deleted row.
    expect(Workbench::query()->where('slug', $workbench->slug)->exists())->toBeFalse()
        ->and(Workbench::withTrashed()->where('slug', $workbench->slug)->first()?->deleted_at)
        ->not->toBeNull();
});

it('REQ-M6-005: POST /workbenches/{slug}/restore clears deleted_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $workbench->delete();

    $this->actingAs($owner)
        ->postJson("/workbenches/{$workbench->slug}/restore")
        ->assertOk();

    $fresh = Workbench::query()->where('slug', $workbench->slug)->first();

    expect($fresh)->not->toBeNull()
        ->and($fresh->deleted_at)->toBeNull();
});

it('REQ-M6-005: non-owner delete returns 403', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->deleteJson("/workbenches/{$workbench->slug}")
        ->assertStatus(403);

    expect(Workbench::query()->whereKey($workbench->id)->exists())->toBeTrue();
});

it('REQ-M6-005: unauthenticated delete returns 401', function (): void {
    $workbench = Workbench::factory()->create();

    $this->deleteJson("/workbenches/{$workbench->slug}")
        ->assertStatus(401);
});

it('REQ-M6-005: unknown slug on delete returns 404', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->deleteJson('/workbenches/does-not-exist')
        ->assertStatus(404);
});

it('REQ-M6-005: non-owner restore returns 403', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $workbench->delete();

    $this->actingAs($stranger)
        ->postJson("/workbenches/{$workbench->slug}/restore")
        ->assertStatus(403);

    expect(Workbench::withTrashed()->whereKey($workbench->id)->first()?->deleted_at)
        ->not->toBeNull();
});

it('REQ-M6-005: MCP writes against a soft-deleted workbench return an error (never resurrect)', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $slug = $workbench->slug;
    $workbench->delete();

    $this->actingAs($owner, 'sanctum');

    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => $slug,
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id']],
            'rows' => [['id' => 1]],
        ],
    ])->assertHasErrors();

    // The soft-deleted workbench stays soft-deleted; no NEW workbench with
    // the same slug is silently created alongside it.
    expect(Workbench::withTrashed()->where('slug', $slug)->count())->toBe(1)
        ->and(Workbench::withTrashed()->where('slug', $slug)->first()?->deleted_at)
        ->not->toBeNull();
});

it('REQ-M6-005: /s/{token} returns 404 while the workbench is soft-deleted', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create([
        'visibility' => SnapshotVisibility::Link->value,
        'share_token' => 'share-token-xyz',
    ]);
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    // Sanity: the share works before deletion.
    $this->get('/s/share-token-xyz')->assertOk();

    $workbench->delete();

    $this->get('/s/share-token-xyz')->assertStatus(404);
});

it('REQ-M6-005: workbenches:prune hard-deletes rows soft-deleted >30 days ago and cascades', function (): void {
    $owner = User::factory()->create();

    $old = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($old)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    $old->delete();
    // Rewind deleted_at to >30 days ago.
    DB::table('workbenches')
        ->where('id', $old->id)
        ->update(['deleted_at' => now()->subDays(31)]);

    $young = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $young->delete();
    DB::table('workbenches')
        ->where('id', $young->id)
        ->update(['deleted_at' => now()->subDays(7)]);

    $this->artisan('workbenches:prune')->assertExitCode(0);

    // Old workbench and every descendant row cascaded out of the DB.
    expect(Workbench::withTrashed()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Snapshot::query()->where('workbench_id', $old->id)->exists())->toBeFalse()
        ->and(DB::table('snapshot_versions')->where('snapshot_id', $snapshot->id)->exists())->toBeFalse();

    // Young workbench (<30 days) stays soft-deleted but is not hard-deleted.
    expect(Workbench::withTrashed()->whereKey($young->id)->exists())->toBeTrue();
});

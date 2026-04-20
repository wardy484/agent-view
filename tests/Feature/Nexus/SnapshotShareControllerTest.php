<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Owner + workbench + rendered snapshot — the common fixture for every
 * REQ-M4-010 test. Returns [owner, workbench, snapshot].
 *
 * @return array{0: User, 1: Workbench, 2: Snapshot}
 */
function ownedSnapshotFixture(): array
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    return [$owner, $workbench, $snapshot->fresh()];
}

function snapshotUrl(Workbench $workbench, Snapshot $snapshot, string $suffix): string
{
    return '/workbenches/'.$workbench->slug.'/snapshots/'.$snapshot->slug.$suffix;
}

// ---------------------------------------------------------------------------
// updateVisibility
// ---------------------------------------------------------------------------

it('REQ-M4-010: updateVisibility flips Private → Link and mints a share token', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();

    $this->actingAs($owner)
        ->patch(snapshotUrl($workbench, $snapshot, '/visibility'), ['visibility' => 'link'])
        ->assertRedirect();

    $fresh = $snapshot->fresh();
    expect($fresh->visibility)->toBe(SnapshotVisibility::Link)
        ->and($fresh->share_token)->toBeString()
        ->and(strlen((string) $fresh->share_token))->toBeGreaterThanOrEqual(32);
});

it('REQ-M4-010: updateVisibility back to Private clears the share token', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();
    $snapshot->setVisibility(SnapshotVisibility::Link);

    $this->actingAs($owner)
        ->patch(snapshotUrl($workbench, $snapshot, '/visibility'), ['visibility' => 'private'])
        ->assertRedirect();

    $fresh = $snapshot->fresh();
    expect($fresh->visibility)->toBe(SnapshotVisibility::Private)
        ->and($fresh->share_token)->toBeNull();
});

it('REQ-M4-010: updateVisibility rejects unknown visibility values with 422', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();

    $this->actingAs($owner)
        ->patch(snapshotUrl($workbench, $snapshot, '/visibility'), ['visibility' => 'public'])
        ->assertSessionHasErrors('visibility');
});

it('REQ-M4-010: updateVisibility returns 403 for non-owner', function (): void {
    [, $workbench, $snapshot] = ownedSnapshotFixture();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(snapshotUrl($workbench, $snapshot, '/visibility'), ['visibility' => 'link'])
        ->assertForbidden();

    expect($snapshot->fresh()->visibility)->toBe(SnapshotVisibility::Private);
});

it('REQ-M4-010: updateVisibility redirects guests to login', function (): void {
    [, $workbench, $snapshot] = ownedSnapshotFixture();

    $this->patch(snapshotUrl($workbench, $snapshot, '/visibility'), ['visibility' => 'link'])
        ->assertRedirect('/login');
});

it('REQ-M4-010: updateVisibility returns 404 when snapshot does not belong to workbench', function (): void {
    [$owner] = ownedSnapshotFixture();
    $otherWorkbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $strayerSnapshot = Snapshot::factory()->for(Workbench::factory()->create(['owner_user_id' => $owner->id]))->create();

    $this->actingAs($owner)
        ->patch(snapshotUrl($otherWorkbench, $strayerSnapshot, '/visibility'), ['visibility' => 'link'])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// rotateToken
// ---------------------------------------------------------------------------

it('REQ-M4-010: rotateToken mints a fresh token and invalidates the old one', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();
    $snapshot->setVisibility(SnapshotVisibility::Link);
    $oldToken = $snapshot->fresh()->share_token;

    $this->actingAs($owner)
        ->post(snapshotUrl($workbench, $snapshot, '/share-token/rotate'))
        ->assertRedirect();

    $newToken = $snapshot->fresh()->share_token;
    expect($newToken)->toBeString()
        ->and($newToken)->not->toBe($oldToken)
        ->and($snapshot->fresh()->visibility)->toBe(SnapshotVisibility::Link);
});

it('REQ-M4-010: rotateToken returns 403 for non-owner', function (): void {
    [, $workbench, $snapshot] = ownedSnapshotFixture();
    $snapshot->setVisibility(SnapshotVisibility::Link);
    $originalToken = $snapshot->fresh()->share_token;
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(snapshotUrl($workbench, $snapshot, '/share-token/rotate'))
        ->assertForbidden();

    expect($snapshot->fresh()->share_token)->toBe($originalToken);
});

// ---------------------------------------------------------------------------
// storeShare
// ---------------------------------------------------------------------------

it('REQ-M4-010: storeShare creates a share with a normalised email', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();

    $this->actingAs($owner)
        ->post(snapshotUrl($workbench, $snapshot, '/shares'), ['email' => '  Ada@Example.COM '])
        ->assertRedirect();

    $share = SnapshotShare::query()->where('snapshot_id', $snapshot->id)->sole();
    expect($share->email)->toBe('ada@example.com')
        ->and($share->granted_by_user_id)->toBe($owner->id)
        ->and($share->revoked_at)->toBeNull();
});

it('REQ-M4-010: storeShare is idempotent when an active share already exists', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();

    $this->actingAs($owner)
        ->post(snapshotUrl($workbench, $snapshot, '/shares'), ['email' => 'ada@example.com'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(snapshotUrl($workbench, $snapshot, '/shares'), ['email' => 'ADA@example.com'])
        ->assertRedirect();

    expect(SnapshotShare::query()->where('snapshot_id', $snapshot->id)->count())->toBe(1);
});

it('REQ-M4-010: storeShare un-revokes a previously revoked share instead of creating a new row', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();

    $share = SnapshotShare::create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);
    $share->revoke();
    expect($share->fresh()->revoked_at)->not->toBeNull();

    $this->actingAs($owner)
        ->post(snapshotUrl($workbench, $snapshot, '/shares'), ['email' => 'ada@example.com'])
        ->assertRedirect();

    expect(SnapshotShare::query()->where('snapshot_id', $snapshot->id)->count())->toBe(1)
        ->and($share->fresh()->revoked_at)->toBeNull();
});

it('REQ-M4-010: storeShare rejects an invalid email with validation errors', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();

    $this->actingAs($owner)
        ->post(snapshotUrl($workbench, $snapshot, '/shares'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    expect(SnapshotShare::query()->where('snapshot_id', $snapshot->id)->count())->toBe(0);
});

it('REQ-M4-010: storeShare returns 403 for non-owner', function (): void {
    [, $workbench, $snapshot] = ownedSnapshotFixture();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(snapshotUrl($workbench, $snapshot, '/shares'), ['email' => 'ada@example.com'])
        ->assertForbidden();

    expect(SnapshotShare::query()->where('snapshot_id', $snapshot->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// destroyShare
// ---------------------------------------------------------------------------

it('REQ-M4-010: destroyShare soft-revokes an active share', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();
    $share = SnapshotShare::create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(snapshotUrl($workbench, $snapshot, '/shares/'.$share->id))
        ->assertRedirect();

    expect($share->fresh()->revoked_at)->not->toBeNull();
});

it('REQ-M4-010: destroyShare is idempotent for already-revoked shares', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();
    $share = SnapshotShare::create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);
    $share->revoke();
    $firstRevokedAt = $share->fresh()->revoked_at;

    $this->actingAs($owner)
        ->delete(snapshotUrl($workbench, $snapshot, '/shares/'.$share->id))
        ->assertRedirect();

    expect($share->fresh()->revoked_at->eq($firstRevokedAt))->toBeTrue();
});

it('REQ-M4-010: destroyShare returns 404 when the share belongs to a different snapshot', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();
    $otherSnapshot = Snapshot::factory()->for($workbench)->create();
    $strayShare = SnapshotShare::create([
        'snapshot_id' => $otherSnapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(snapshotUrl($workbench, $snapshot, '/shares/'.$strayShare->id))
        ->assertNotFound();

    expect($strayShare->fresh()->revoked_at)->toBeNull();
});

it('REQ-M4-010: destroyShare returns 403 for non-owner', function (): void {
    [$owner, $workbench, $snapshot] = ownedSnapshotFixture();
    $share = SnapshotShare::create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->delete(snapshotUrl($workbench, $snapshot, '/shares/'.$share->id))
        ->assertForbidden();

    expect($share->fresh()->revoked_at)->toBeNull();
});

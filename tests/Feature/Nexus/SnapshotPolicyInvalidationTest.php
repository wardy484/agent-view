<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Policies\SnapshotPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * REQ-M4-009: `Snapshot::setVisibility()` rotation and `SnapshotShare::revoke()`
 * both take effect immediately — the very next policy call inside the same
 * request must see the new decision with no stale cache.
 *
 * These tests all run the transition and the re-check inside a single
 * PHP request lifecycle (one test = one request).
 */
function freshPolicy(): SnapshotPolicy
{
    return app(SnapshotPolicy::class);
}

function buildSnapshot(User $owner, SnapshotVisibility $visibility = SnapshotVisibility::Private): Snapshot
{
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    if ($visibility !== SnapshotVisibility::Private) {
        $snapshot->setVisibility($visibility);
    }

    return $snapshot->fresh();
}

it('REQ-M4-009: rotating the link token invalidates the previous token in the same request', function (): void {
    $owner = User::factory()->create();
    $snapshot = buildSnapshot($owner, SnapshotVisibility::Link);

    $original = $snapshot->share_token;
    expect(freshPolicy()->view(null, $snapshot, $original))->toBeTrue();

    // Rotate by toggling off and back on.
    $snapshot->setVisibility(SnapshotVisibility::Private);
    $snapshot->setVisibility(SnapshotVisibility::Link);
    $rotated = $snapshot->share_token;

    expect($rotated)->not->toBe($original)
        ->and(freshPolicy()->view(null, $snapshot, $original))->toBeFalse()
        ->and(freshPolicy()->view(null, $snapshot, $rotated))->toBeTrue();
});

it('REQ-M4-009: setting visibility back to Private immediately rejects the old token', function (): void {
    $owner = User::factory()->create();
    $snapshot = buildSnapshot($owner, SnapshotVisibility::Link);

    $token = $snapshot->share_token;
    expect(freshPolicy()->view(null, $snapshot, $token))->toBeTrue();

    $snapshot->setVisibility(SnapshotVisibility::Private);

    expect($snapshot->share_token)->toBeNull()
        ->and(freshPolicy()->view(null, $snapshot, $token))->toBeFalse();
});

it('REQ-M4-009: revoking a share immediately drops access for that user', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = buildSnapshot($owner, SnapshotVisibility::Shared);

    $share = SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    expect(freshPolicy()->view($invitee, $snapshot))->toBeTrue();

    $share->revoke();

    expect(freshPolicy()->view($invitee, $snapshot))->toBeFalse();
});

it('REQ-M4-009: granting a fresh share after a revoke immediately restores access', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = buildSnapshot($owner, SnapshotVisibility::Shared);

    $share = SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);
    $share->revoke();
    expect(freshPolicy()->view($invitee, $snapshot))->toBeFalse();

    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    expect(freshPolicy()->view($invitee, $snapshot))->toBeTrue();
});

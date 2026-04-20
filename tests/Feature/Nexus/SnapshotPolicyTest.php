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

function snapshotPolicy(): SnapshotPolicy
{
    return app(SnapshotPolicy::class);
}

function seedSnapshot(User $owner, SnapshotVisibility $visibility = SnapshotVisibility::Private): Snapshot
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

it('REQ-M4-005: owner can view their own snapshot regardless of visibility', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedSnapshot($owner);

    expect(snapshotPolicy()->view($owner, $snapshot))->toBeTrue();
});

it('REQ-M4-005: a random signed-in user cannot view a private snapshot', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedSnapshot($owner);

    expect(snapshotPolicy()->view($stranger, $snapshot))->toBeFalse();
});

it('REQ-M4-005: a guest cannot view a private snapshot', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedSnapshot($owner);

    expect(snapshotPolicy()->view(null, $snapshot))->toBeFalse();
});

it('REQ-M4-005: anyone with the correct token can view a link-shared snapshot', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedSnapshot($owner, SnapshotVisibility::Link);

    expect(snapshotPolicy()->view(null, $snapshot, $snapshot->share_token))->toBeTrue()
        ->and(snapshotPolicy()->view(User::factory()->create(), $snapshot, $snapshot->share_token))->toBeTrue();
});

it('REQ-M4-005: a wrong token never grants access', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedSnapshot($owner, SnapshotVisibility::Link);

    expect(snapshotPolicy()->view(null, $snapshot, 'definitely-wrong'))->toBeFalse()
        ->and(snapshotPolicy()->view(null, $snapshot, null))->toBeFalse();
});

it('REQ-M4-005: shared visibility grants access by matching user_id', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = seedSnapshot($owner, SnapshotVisibility::Shared);

    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    expect(snapshotPolicy()->view($invitee, $snapshot))->toBeTrue();
});

it('REQ-M4-005: shared visibility grants access by matching email even if user_id is null', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'ada@example.com']);
    $snapshot = seedSnapshot($owner, SnapshotVisibility::Shared);

    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ADA@Example.COM',
        'user_id' => null,
        'granted_by_user_id' => $owner->id,
    ]);

    expect(snapshotPolicy()->view($invitee, $snapshot))->toBeTrue();
});

it('REQ-M4-005: revoked shares do not grant access', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = seedSnapshot($owner, SnapshotVisibility::Shared);

    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->revoked()
        ->create(['granted_by_user_id' => $owner->id]);

    expect(snapshotPolicy()->view($invitee, $snapshot))->toBeFalse();
});

it('REQ-M4-005: owner-null workbenches reject every non-owner viewer', function (): void {
    // System-owned (local stdio) workbenches can never be shared.
    $workbench = Workbench::factory()->create(['owner_user_id' => null]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    expect(snapshotPolicy()->view(User::factory()->create(), $snapshot))->toBeFalse()
        ->and(snapshotPolicy()->view(null, $snapshot))->toBeFalse();
});

it('REQ-M4-005: authenticated snapshot route returns 403 for non-owner without a share', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedSnapshot($owner);

    $this->actingAs($stranger)
        ->get(route('workbench.snapshot.show', [
            'workbench' => $snapshot->workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertForbidden();
});

it('REQ-M4-005: authenticated snapshot route succeeds for owner', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedSnapshot($owner);

    $this->actingAs($owner)
        ->get(route('workbench.snapshot.show', [
            'workbench' => $snapshot->workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk();
});

it('REQ-M4-005: authenticated snapshot route succeeds for an unrevoked share', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = seedSnapshot($owner, SnapshotVisibility::Shared);

    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($invitee)
        ->get(route('workbench.snapshot.show', [
            'workbench' => $snapshot->workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk();
});

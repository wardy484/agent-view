<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function makeVersionedSnapshot(Workbench $workbench, ?string $slug = null): Snapshot
{
    $snapshot = Snapshot::factory()->for($workbench)->create(
        $slug !== null ? ['slug' => $slug] : []
    );
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    return $snapshot->fresh();
}

it('REQ-M4-007: signed-in users see a shared_with_me list on every Inertia page', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $wbA = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $wbB = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $shared1 = makeVersionedSnapshot($wbA, 'alpha');
    $shared1->setVisibility(SnapshotVisibility::Shared);
    $shared2 = makeVersionedSnapshot($wbB, 'beta');
    $shared2->setVisibility(SnapshotVisibility::Shared);

    SnapshotShare::factory()->for($shared1)->forUser($viewer)
        ->create(['granted_by_user_id' => $owner->id]);
    SnapshotShare::factory()->for($shared2)->forUser($viewer)
        ->create(['granted_by_user_id' => $owner->id]);

    // Plus a share that's revoked — must NOT show up.
    $revokedSnap = makeVersionedSnapshot($wbA);
    $revokedSnap->setVisibility(SnapshotVisibility::Shared);
    SnapshotShare::factory()->for($revokedSnap)->forUser($viewer)->revoked()
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($viewer)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sharing.shared_with_me', 2)
            ->where('sharing.shared_with_me.0.workbench_slug', fn ($slug) => in_array($slug, [$wbA->slug, $wbB->slug], true))
        );
});

it('REQ-M4-007: guests receive empty sharing payloads', function (): void {
    $this->withoutVite()
        ->get(route('dashboard'))
        ->assertRedirect(); // dashboard is auth-gated; guests bounce.
});

it('REQ-M4-007: email-only share rows (user_id=null) still surface to the matching user', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create(['email' => 'ada@example.com']);
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = makeVersionedSnapshot($workbench);
    $snapshot->setVisibility(SnapshotVisibility::Shared);

    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'user_id' => null,
        'granted_by_user_id' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sharing.shared_with_me', 1)
        );
});

it('REQ-M4-007: owner sidebar exposes share_count + has_link badges for owned snapshots', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $quiet = makeVersionedSnapshot($workbench, 'quiet');
    $withShares = makeVersionedSnapshot($workbench, 'busy');
    $withShares->setVisibility(SnapshotVisibility::Shared);
    $withLink = makeVersionedSnapshot($workbench, 'linked');
    $withLink->setVisibility(SnapshotVisibility::Link);

    $invitee = User::factory()->create();
    SnapshotShare::factory()->for($withShares)->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);
    SnapshotShare::factory()->for($withShares)->count(2)
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sharing.owned_badges')
            ->where('sharing.owned_badges', function ($badges) use ($quiet, $withShares, $withLink) {
                $bySlug = collect($badges)->keyBy('snapshot_slug');

                return ($bySlug[$quiet->slug]['share_count'] ?? null) === 0
                    && ($bySlug[$quiet->slug]['has_link'] ?? null) === false
                    && ($bySlug[$withShares->slug]['share_count'] ?? null) === 3
                    && ($bySlug[$withShares->slug]['has_link'] ?? null) === false
                    && ($bySlug[$withLink->slug]['share_count'] ?? null) === 0
                    && ($bySlug[$withLink->slug]['has_link'] ?? null) === true;
            })
        );
});

it('REQ-M4-007: revoked shares do not contribute to the share_count badge', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = makeVersionedSnapshot($workbench);
    $snapshot->setVisibility(SnapshotVisibility::Shared);

    SnapshotShare::factory()->for($snapshot)->revoked()
        ->create(['granted_by_user_id' => $owner->id]);
    SnapshotShare::factory()->for($snapshot)->revoked()
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sharing.owned_badges', function ($badges) use ($snapshot) {
                $entry = collect($badges)->firstWhere('snapshot_slug', $snapshot->slug);

                return ($entry['share_count'] ?? null) === 0;
            })
        );
});

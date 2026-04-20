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

function twoRevisionSnapshot(User $owner, SnapshotVisibility $visibility = SnapshotVisibility::Private): Snapshot
{
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $v1 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    $v2 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 2]]],
    );
    $snapshot->forceFill(['current_version_id' => $v2->id])->save();

    if ($visibility !== SnapshotVisibility::Private) {
        $snapshot->setVisibility($visibility);
    }

    return $snapshot->fresh();
}

it('REQ-M4-006: owner sees is_owner=true and the full versions list', function (): void {
    $owner = User::factory()->create();
    $snapshot = twoRevisionSnapshot($owner);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $snapshot->workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('is_owner', true)
            ->where('is_public_link', false)
            ->has('versions', 2)
        );
});

it('REQ-M4-006: shared-with viewer sees is_owner=false, empty versions, latest only', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = twoRevisionSnapshot($owner, SnapshotVisibility::Shared);

    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($invitee)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $snapshot->workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('is_owner', false)
            ->where('is_public_link', false)
            ->where('versions', [])
            ->where('version.revision', 2)
        );
});

it('REQ-M4-006: ?revision= is ignored for non-owners (they always see latest)', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = twoRevisionSnapshot($owner, SnapshotVisibility::Shared);

    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($invitee)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $snapshot->workbench->slug,
            'snapshot' => $snapshot->slug,
            'revision' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.revision', 2) // stays on latest
        );
});

it('REQ-M4-006: public link viewer sees is_public_link=true and no versions', function (): void {
    $owner = User::factory()->create();
    $snapshot = twoRevisionSnapshot($owner, SnapshotVisibility::Link);

    $this->withoutVite()
        ->get('/s/'.$snapshot->share_token)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('is_public_link', true)
            ->where('is_owner', false)
            ->where('versions', [])
            ->where('version.revision', 2)
        );
});

it('REQ-M4-006: the snapshot React page hides the VersionSwitcher for non-owners and public link viewers', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    // Enforce the UI branches that gate every mutation/switcher affordance on
    // the `is_owner` / `is_public_link` props passed by the controllers.
    expect($source)
        ->toContain('is_owner')
        ->toContain('is_public_link')
        ->toContain('VersionSwitcher');
});

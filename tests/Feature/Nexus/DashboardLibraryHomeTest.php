<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function appendVersion(Snapshot $snapshot, ?string $createdAt = null): void
{
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    if ($createdAt !== null) {
        DB::table('snapshot_versions')
            ->where('id', $snapshot->fresh()->current_version_id)
            ->update(['created_at' => $createdAt]);
    }
}

it('REQ-M8-001: dashboard payload exposes recentSnapshots and workbenches and drops legacy KPI/sample props', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('recentSnapshots')
            ->has('workbenches')
            ->missing('kpis')
            ->missing('viewTypeSamples')
        );
});

it('REQ-M8-002: recents rail caps at 5, orders by latest revision time desc, scopes to the signed-in owner', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $wb = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    foreach (range(1, 7) as $i) {
        $s = Snapshot::factory()->for($wb)->create(['slug' => "owned-{$i}"]);
        appendVersion($s, now()->subMinutes(7 - $i)->toDateTimeString());
    }

    $strangerWb = Workbench::factory()->create(['owner_user_id' => $stranger->id]);
    $strangerSnap = Snapshot::factory()->for($strangerWb)->create(['slug' => 'stranger-snap']);
    appendVersion($strangerSnap, now()->toDateTimeString());

    $systemWb = Workbench::factory()->create(['owner_user_id' => null]);
    $systemSnap = Snapshot::factory()->for($systemWb)->create(['slug' => 'system-snap']);
    appendVersion($systemSnap, now()->toDateTimeString());

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('recentSnapshots', 5)
            ->where('recentSnapshots.0.snapshot_slug', 'owned-7')
            ->where('recentSnapshots.4.snapshot_slug', 'owned-3')
            ->etc()
        );
});

it('REQ-M8-003: workbenches list is sorted by last activity desc and excludes system-owned workbenches', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $stale = Workbench::factory()->create(['owner_user_id' => $owner->id, 'name' => 'stale-wb']);
    $active = Workbench::factory()->create(['owner_user_id' => $owner->id, 'name' => 'active-wb']);
    $empty = Workbench::factory()->create(['owner_user_id' => $owner->id, 'name' => 'empty-wb']);
    Workbench::factory()->create(['owner_user_id' => $stranger->id, 'name' => 'stranger-wb']);
    Workbench::factory()->create(['owner_user_id' => null, 'name' => 'system-wb']);

    $staleSnap = Snapshot::factory()->for($stale)->create();
    appendVersion($staleSnap, now()->subDays(3)->toDateTimeString());

    $activeSnap = Snapshot::factory()->for($active)->create();
    appendVersion($activeSnap, now()->subMinutes(5)->toDateTimeString());

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('workbenches', 3)
            ->where('workbenches.0.name', 'active-wb')
            ->where('workbenches.0.snapshot_count', 1)
            ->where('workbenches.1.name', 'stale-wb')
            ->where('workbenches.2.name', 'empty-wb')
            ->where('workbenches.2.snapshot_count', 0)
            ->etc()
        );
});

it('REQ-M8-004: empty state is signalled when the signed-in user owns zero workbenches', function (): void {
    $newcomer = User::factory()->create();

    Workbench::factory()->create(['owner_user_id' => User::factory(), 'name' => 'someone-elses']);
    Workbench::factory()->create(['owner_user_id' => null, 'name' => 'system-only']);

    $this->actingAs($newcomer)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('workbenches', 0)
            ->has('recentSnapshots', 0)
        );
});

it('REQ-M8-005: when the user owns workbenches the page renders dashboard with both props populated', function (): void {
    $owner = User::factory()->create();

    $wb = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snap = Snapshot::factory()->for($wb)->create();
    appendVersion($snap, now()->toDateTimeString());

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('recentSnapshots', 1)
            ->has('workbenches', 1)
            ->where('workbenches.0.snapshot_count', 1)
            ->etc()
        );
});

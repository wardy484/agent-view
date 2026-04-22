<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M6-007: dashboard exposes ownedWorkbenches sorted by last_activity_at desc with organisational state', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    // Owned: one active, one pinned, one archived, one trashed, one freshest.
    Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Stale Workbench',
        'slug' => 'stale',
        'last_activity_at' => now()->subMonth(),
    ]);
    Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Pinned Workbench',
        'slug' => 'pinned',
        'pinned_at' => now(),
        'last_activity_at' => now()->subWeek(),
    ]);
    Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Archived Workbench',
        'slug' => 'archived',
        'archived_at' => now()->subDay(),
        'last_activity_at' => now()->subDay(),
    ]);
    $trashed = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Trashed Workbench',
        'slug' => 'trashed',
        'last_activity_at' => now()->subHour(),
    ]);
    $trashed->delete();
    Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Freshest Workbench',
        'slug' => 'freshest',
        'last_activity_at' => now(),
    ]);

    // Stranger's workbench — must NEVER appear in this user's payload.
    Workbench::factory()->create([
        'owner_user_id' => $other->id,
        'slug' => 'stranger',
        'name' => "Stranger's",
    ]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('ownedWorkbenches', 5)
            ->where('ownedWorkbenches.0.slug', 'freshest')
            ->where('ownedWorkbenches.1.slug', 'trashed')
            ->where('ownedWorkbenches.2.slug', 'archived')
            ->where('ownedWorkbenches.3.slug', 'pinned')
            ->where('ownedWorkbenches.4.slug', 'stale')
            ->where('ownedWorkbenches.0.deleted_at', null)
            ->where('ownedWorkbenches.0.archived_at', null)
            ->where('ownedWorkbenches.0.pinned_at', null)
            ->where('ownedWorkbenches.1.deleted_at', fn ($v) => $v !== null)
            ->where('ownedWorkbenches.2.archived_at', fn ($v) => $v !== null)
            ->where('ownedWorkbenches.3.pinned_at', fn ($v) => $v !== null)
            ->where('ownedWorkbenches', fn ($list) => collect($list)
                ->pluck('slug')
                ->doesntContain('stranger'))
        );
});

it('REQ-M6-007: dashboard ownedWorkbenches includes latest_snapshot_slug and snapshot_count', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'wb',
    ]);
    $older = Snapshot::factory()->for($workbench)->create(['slug' => 'older']);
    $older->forceFill(['updated_at' => now()->subDay()])->save();
    $newer = Snapshot::factory()->for($workbench)->create(['slug' => 'newer']);
    $newer->forceFill(['updated_at' => now()])->save();

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ownedWorkbenches.0.slug', 'wb')
            ->where('ownedWorkbenches.0.snapshot_count', 2)
            ->where('ownedWorkbenches.0.latest_snapshot_slug', 'newer')
        );
});

it('REQ-M6-007: unauthenticated visitors get redirected (no ownedWorkbenches leak)', function (): void {
    $this->withoutVite()
        ->get(route('dashboard'))
        ->assertRedirect();
});

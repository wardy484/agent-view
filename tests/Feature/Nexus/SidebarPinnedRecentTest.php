<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M6-008: pinned workbenches appear ordered by pinned_at desc', function (): void {
    $owner = User::factory()->create();

    $older = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Older Pin',
        'pinned_at' => now()->subDays(3),
    ]);
    $newer = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Newer Pin',
        'pinned_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('nav.pinned', 2)
            ->where('nav.pinned.0.slug', $newer->slug)
            ->where('nav.pinned.1.slug', $older->slug)
        );
});

it('REQ-M6-008: recent lists up to 5 non-pinned non-archived workbenches by last_activity_at desc', function (): void {
    $owner = User::factory()->create();

    foreach (range(1, 7) as $i) {
        Workbench::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => "Wb {$i}",
            'last_activity_at' => now()->subHours(10 - $i),
        ]);
    }

    // A pinned workbench — must not appear in recent.
    Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Pinned Wb',
        'pinned_at' => now(),
        'last_activity_at' => now(),
    ]);

    // An archived workbench — must not appear in recent.
    Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Archived Wb',
        'archived_at' => now(),
        'last_activity_at' => now(),
    ]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('nav.recent', 5)
            ->where('nav.recent.0.name', 'Wb 7')
            ->where('nav.recent.4.name', 'Wb 3')
            ->where('nav.recent', function ($recent): bool {
                $names = collect($recent)->pluck('name')->all();

                return ! in_array('Pinned Wb', $names, true)
                    && ! in_array('Archived Wb', $names, true);
            })
        );
});

it('REQ-M6-008: another user\'s pinned workbenches never leak into this user\'s nav', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Workbench::factory()->create([
        'owner_user_id' => $other->id,
        'pinned_at' => now(),
    ]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('nav.pinned', 0)
            ->has('nav.recent', 0)
        );
});

it('REQ-M6-008: guests receive empty pinned and recent arrays', function (): void {
    Workbench::factory()->create([
        'owner_user_id' => User::factory(),
        'pinned_at' => now(),
    ]);

    // Dashboard is auth-gated; guests bounce. We exercise the middleware via
    // a publicly-reachable page instead.
    $this->withoutVite()
        ->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('nav.pinned', 0)
            ->has('nav.recent', 0)
        );
});

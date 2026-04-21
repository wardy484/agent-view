<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M6-003: POST /workbenches/{slug}/pin sets pinned_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'pinned_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$workbench->slug}/pin")
        ->assertOk();

    expect($workbench->fresh()->pinned_at)->not->toBeNull();
});

it('REQ-M6-003: DELETE /workbenches/{slug}/pin clears pinned_at', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'pinned_at' => now(),
    ]);

    $this->actingAs($owner)
        ->deleteJson("/workbenches/{$workbench->slug}/pin")
        ->assertOk();

    expect($workbench->fresh()->pinned_at)->toBeNull();
});

it('REQ-M6-003: pinning an archived workbench auto-unarchives it', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'archived_at' => now(),
        'pinned_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$workbench->slug}/pin")
        ->assertOk();

    $workbench->refresh();

    expect($workbench->pinned_at)->not->toBeNull()
        ->and($workbench->archived_at)->toBeNull();
});

it('REQ-M6-003: a user may pin at most 12 workbenches — the 13th returns 422', function (): void {
    $owner = User::factory()->create();

    Workbench::factory()
        ->count(12)
        ->create(['owner_user_id' => $owner->id, 'pinned_at' => now()]);

    $thirteenth = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'pinned_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$thirteenth->slug}/pin")
        ->assertStatus(422)
        ->assertJsonPath('errors.pinned_at.0', 'You can pin at most 12 workbenches.');

    expect($thirteenth->fresh()->pinned_at)->toBeNull();
});

it('REQ-M6-003: pin cap counts only this user — other users are unaffected', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Workbench::factory()
        ->count(12)
        ->create(['owner_user_id' => $other->id, 'pinned_at' => now()]);

    $mine = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'pinned_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$mine->slug}/pin")
        ->assertOk();

    expect($mine->fresh()->pinned_at)->not->toBeNull();
});

it('REQ-M6-003: pinning an already-pinned workbench is idempotent and does not consume a slot', function (): void {
    $owner = User::factory()->create();

    // Fill 10 slots, then the 11th is the one we re-pin twice.
    Workbench::factory()
        ->count(10)
        ->create(['owner_user_id' => $owner->id, 'pinned_at' => now()]);

    $target = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'pinned_at' => now()->subMinute(),
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$target->slug}/pin")
        ->assertOk();

    // Now verify we can still pin a 12th brand-new bench.
    $another = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'pinned_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/workbenches/{$another->slug}/pin")
        ->assertOk();
});

it('REQ-M6-003: non-owner pin attempt returns 403', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->postJson("/workbenches/{$workbench->slug}/pin")
        ->assertStatus(403);
});

it('REQ-M6-003: unauthenticated pin attempt returns 401', function (): void {
    $workbench = Workbench::factory()->create();

    $this->postJson("/workbenches/{$workbench->slug}/pin")
        ->assertStatus(401);
});

it('REQ-M6-003: unknown slug returns 404', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/workbenches/nope/pin')
        ->assertStatus(404);
});

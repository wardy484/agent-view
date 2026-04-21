<?php

declare(strict_types=1);

use App\Models\McpCallLog;
use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M6-002: owner renames a workbench via PATCH and slug stays stable', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'original-slug',
        'name' => 'Original Name',
    ]);

    $response = $this->actingAs($owner)
        ->patchJson("/workbenches/{$workbench->slug}", ['name' => '  Shiny New Name  ']);

    $response->assertOk();

    $workbench->refresh();

    expect($workbench->name)->toBe('Shiny New Name')
        ->and($workbench->slug)->toBe('original-slug');
});

it('REQ-M6-002: rename writes an mcp_call_logs audit entry', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->patchJson("/workbenches/{$workbench->slug}", ['name' => 'Renamed'])
        ->assertOk();

    $log = McpCallLog::query()
        ->where('tool_name', 'workbench.rename')
        ->where('workbench_id', $workbench->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->status)->toBe('ok');
});

it('REQ-M6-002: rejects names that fail validation', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    // Empty after trim.
    $this->actingAs($owner)
        ->patchJson("/workbenches/{$workbench->slug}", ['name' => '   '])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    // Over 120 characters.
    $this->actingAs($owner)
        ->patchJson("/workbenches/{$workbench->slug}", ['name' => str_repeat('a', 121)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    // Missing.
    $this->actingAs($owner)
        ->patchJson("/workbenches/{$workbench->slug}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('REQ-M6-002: a non-owner gets 403', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->patchJson("/workbenches/{$workbench->slug}", ['name' => 'Stolen'])
        ->assertStatus(403);

    expect($workbench->fresh()->name)->not->toBe('Stolen');
});

it('REQ-M6-002: null-owner workbenches cannot be renamed', function (): void {
    $user = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => null]);

    $this->actingAs($user)
        ->patchJson("/workbenches/{$workbench->slug}", ['name' => 'Hijacked'])
        ->assertStatus(403);

    expect($workbench->fresh()->name)->not->toBe('Hijacked');
});

it('REQ-M6-002: unknown slug returns 404', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->patchJson('/workbenches/does-not-exist', ['name' => 'Ghost'])
        ->assertStatus(404);
});

it('REQ-M6-002: unauthenticated callers get 401/redirected', function (): void {
    $workbench = Workbench::factory()->create();

    $this->patchJson("/workbenches/{$workbench->slug}", ['name' => 'Anonymous'])
        ->assertStatus(401);
});

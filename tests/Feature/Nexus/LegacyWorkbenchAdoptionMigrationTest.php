<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Re-runs the legacy-adoption migration against hand-crafted table state and
 * asserts the two heuristics:
 *   1. mcp_call_logs.user_id wins (earliest authenticated row per workbench).
 *   2. Single-user fallback claims whatever step 1 leaves behind.
 */
function runLegacyAdoptionMigration(): void
{
    $path = database_path('migrations/2026_04_20_034542_backfill_remaining_null_owner_workbenches.php');
    (require $path)->up();
}

it('claims null-owner workbenches from the earliest authenticated mcp_call_log', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $wb = Workbench::factory()->create(['owner_user_id' => null]);

    DB::table('mcp_call_logs')->insert([
        [
            'workbench_id' => $wb->id,
            'user_id' => $alice->id,
            'tool_name' => 'present_structured_data',
            'created_at' => now()->subHour(),
        ],
        [
            'workbench_id' => $wb->id,
            'user_id' => $bob->id,
            'tool_name' => 'present_structured_data',
            'created_at' => now(),
        ],
    ]);

    runLegacyAdoptionMigration();

    expect($wb->fresh()->owner_user_id)->toBe($alice->id);
});

it('falls back to the sole user when there is no authenticated mcp_call_log', function (): void {
    $alice = User::factory()->create();
    $wb = Workbench::factory()->create(['owner_user_id' => null]);

    runLegacyAdoptionMigration();

    expect($wb->fresh()->owner_user_id)->toBe($alice->id);
});

it('leaves workbenches null-owned when there is ambiguity (0 or >1 users and no logs)', function (): void {
    User::factory()->count(2)->create();
    $wb = Workbench::factory()->create(['owner_user_id' => null]);

    runLegacyAdoptionMigration();

    expect($wb->fresh()->owner_user_id)->toBeNull();
});

it('never overwrites an existing owner', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Workbench::factory() assigns an owner via factory default; force bob.
    $wb = Workbench::factory()->create(['owner_user_id' => $bob->id]);

    DB::table('mcp_call_logs')->insert([
        'workbench_id' => $wb->id,
        'user_id' => $alice->id,
        'tool_name' => 'present_structured_data',
        'created_at' => now(),
    ]);

    runLegacyAdoptionMigration();

    expect($wb->fresh()->owner_user_id)->toBe($bob->id);
});

it('is idempotent — running twice leaves the same result', function (): void {
    $alice = User::factory()->create();
    $wb = Workbench::factory()->create(['owner_user_id' => null]);

    runLegacyAdoptionMigration();
    runLegacyAdoptionMigration();

    expect($wb->fresh()->owner_user_id)->toBe($alice->id);
});

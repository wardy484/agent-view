<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\McpCallLog;
use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('REQ-M4-000: workbenches have a nullable owner_user_id column', function (): void {
    expect(Workbench::query()->getConnection()->getSchemaBuilder()->hasColumn('workbenches', 'owner_user_id'))
        ->toBeTrue();

    // Column is nullable so local stdio / system flows can create workbenches without a user.
    $workbench = Workbench::factory()->create(['owner_user_id' => null]);

    expect($workbench->owner_user_id)->toBeNull();
});

it('REQ-M4-000: workbench owner relation resolves to the owning user', function (): void {
    $user = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $user->id]);

    expect($workbench->owner)->not->toBeNull()
        ->and($workbench->owner->id)->toBe($user->id);
});

it('REQ-M4-000: PresentStructuredData sets owner_user_id to the authenticated user on workbench creation', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'owned-bench',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ])->assertOk();

    $workbench = Workbench::query()->where('slug', 'owned-bench')->first();

    expect($workbench)->not->toBeNull()
        ->and((int) $workbench->owner_user_id)->toBe($user->id);
});

it('REQ-M4-000: PresentStructuredData leaves owner_user_id null when no user is authenticated (local stdio)', function (): void {
    // No Sanctum::actingAs — simulates the unauthenticated local stdio server.
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'anon-bench',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ])->assertOk();

    $workbench = Workbench::query()->where('slug', 'anon-bench')->first();

    expect($workbench)->not->toBeNull()
        ->and($workbench->owner_user_id)->toBeNull();
});

it('REQ-M4-000: PresentStructuredData does not reassign owner on subsequent calls', function (): void {
    $originalOwner = User::factory()->create();
    $someoneElse = User::factory()->create();

    Sanctum::actingAs($originalOwner);
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'locked-bench',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ])->assertOk();

    Sanctum::actingAs($someoneElse);
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'locked-bench',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 2]],
        ],
    ])->assertOk();

    $workbench = Workbench::query()->where('slug', 'locked-bench')->first();

    expect((int) $workbench->owner_user_id)->toBe($originalOwner->id);
});

it('REQ-M4-000: backfill migration assigns owner from earliest non-null mcp_call_logs.user_id', function (): void {
    // Simulate the pre-migration state: a workbench with owner_user_id = null
    // and several mcp_call_logs rows, the earliest of which references a user.
    $earlyUser = User::factory()->create();
    $laterUser = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => null]);

    McpCallLog::query()->create([
        'tool_name' => 'present_structured_data',
        'duration_ms' => 10,
        'status' => 'ok',
        'payload_bytes' => 100,
        'user_id' => null, // legacy row, no user captured
        'workbench_id' => $workbench->id,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    McpCallLog::query()->create([
        'tool_name' => 'present_structured_data',
        'duration_ms' => 10,
        'status' => 'ok',
        'payload_bytes' => 100,
        'user_id' => $earlyUser->id,
        'workbench_id' => $workbench->id,
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);
    McpCallLog::query()->create([
        'tool_name' => 'present_structured_data',
        'duration_ms' => 10,
        'status' => 'ok',
        'payload_bytes' => 100,
        'user_id' => $laterUser->id,
        'workbench_id' => $workbench->id,
        'created_at' => now()->subDays(1),
        'updated_at' => now()->subDays(1),
    ]);

    \App\Nexus\WorkbenchOwnerBackfill::run();

    expect($workbench->fresh()->owner_user_id)->toBe($earlyUser->id);
});

it('REQ-M4-000: backfill leaves owner null when no mcp_call_logs row has a user', function (): void {
    $workbench = Workbench::factory()->create(['owner_user_id' => null]);

    McpCallLog::query()->create([
        'tool_name' => 'present_structured_data',
        'duration_ms' => 10,
        'status' => 'ok',
        'payload_bytes' => 100,
        'user_id' => null,
        'workbench_id' => $workbench->id,
    ]);

    \App\Nexus\WorkbenchOwnerBackfill::run();

    expect($workbench->fresh()->owner_user_id)->toBeNull();
});

<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\GetFollowUpContext;
use App\Models\FollowUpContext;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('REQ-M3-003: returns all unconsumed follow_up_contexts rows for a workbench and marks them consumed', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);

    $a = FollowUpContext::query()->create([
        'workbench_id' => $workbench->id,
        'payload' => ['selection' => [['row' => 1]]],
    ]);
    $b = FollowUpContext::query()->create([
        'workbench_id' => $workbench->id,
        'payload' => ['selection' => [['row' => 2]]],
    ]);

    $response = NexusServer::tool(GetFollowUpContext::class, [
        'workbench_slug' => $workbench->slug,
    ])->assertOk();

    $response->assertStructuredContent(function (AssertableJson $json) use ($workbench): void {
        $json->where('workbench_slug', $workbench->slug)
            ->has('contexts', 2)
            ->etc();
    });

    expect($a->refresh()->consumed_at)->not->toBeNull();
    expect($b->refresh()->consumed_at)->not->toBeNull();
});

it('REQ-M3-005: a consumed row is never returned twice (idempotent)', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-bravo']);

    FollowUpContext::query()->create([
        'workbench_id' => $workbench->id,
        'payload' => ['selection' => [['row' => 1]]],
    ]);

    // First call consumes the row.
    NexusServer::tool(GetFollowUpContext::class, [
        'workbench_slug' => $workbench->slug,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('contexts', 1)->etc();
        });

    // Second call must return zero contexts — the row was already consumed.
    NexusServer::tool(GetFollowUpContext::class, [
        'workbench_slug' => $workbench->slug,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('contexts', 0)->etc();
        });
});

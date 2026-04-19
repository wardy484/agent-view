<?php

declare(strict_types=1);

use App\Models\McpCallLog;
use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\AgentActivityDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M3-007: Agent Activity dashboard is a singleton snapshot per workbench rendered via snapshot.tsx', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);

    McpCallLog::query()->create([
        'tool_name' => 'present_structured_data',
        'duration_ms' => 5,
        'status' => 'ok',
        'payload_bytes' => 128,
    ]);

    $this->withoutVite()
        ->get(route('workbench.agent-activity', ['workbench' => $workbench->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('snapshot.slug', AgentActivityDashboard::SLUG)
            ->where('version.view_type', 'table')
        );

    // Singleton: repeated visits do not create additional snapshot rows.
    $this->withoutVite()
        ->get(route('workbench.agent-activity', ['workbench' => $workbench->slug]))
        ->assertOk();

    $count = Snapshot::query()
        ->where('workbench_id', $workbench->id)
        ->where('slug', AgentActivityDashboard::SLUG)
        ->count();

    expect($count)->toBe(1);
});

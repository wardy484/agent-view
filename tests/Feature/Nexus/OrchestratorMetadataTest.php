<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\GetFollowUpContext;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\FollowUpContext;
use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\OrchestratorMetadataTooLargeException;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M7-004: persists metadata.orchestrator on append and round-trips it via SnapshotController@show', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-orch']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'engineers']);

    $orchestrator = [
        'scout' => [
            'files' => ['app/Foo.php', 'app/Bar.php'],
            'next_req' => 'REQ-M7-004',
        ],
        'dag' => [
            ['id' => 'a', 'depends_on' => []],
            ['id' => 'b', 'depends_on' => ['a']],
        ],
        'in_flight' => ['agent-12', 'agent-19'],
        'last_failure' => null,
    ];

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
        metadata: ['orchestrator' => $orchestrator, 'summary' => 'wired up scout'],
    );

    expect($version->metadata['orchestrator'])->toEqual($orchestrator);

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.metadata.orchestrator', $orchestrator)
            ->where('version.metadata.summary', 'wired up scout')
        );
});

it('REQ-M7-004: persists metadata.orchestrator and round-trips it via get_follow_up_context MCP tool', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-orch-mcp']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'live-board']);

    $orchestrator = [
        'cursor' => 'page-3',
        'subagents' => ['s1' => 'running', 's2' => 'queued'],
    ];

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
        metadata: ['orchestrator' => $orchestrator],
    );

    FollowUpContext::query()->create([
        'workbench_id' => $workbench->id,
        'snapshot_id' => $snapshot->id,
        'payload' => ['selection' => [['row_key' => 1]]],
    ]);

    NexusServer::tool(GetFollowUpContext::class, [
        'workbench_slug' => $workbench->slug,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($workbench, $orchestrator): void {
            $json->where('workbench_slug', $workbench->slug)
                ->has('contexts', 1)
                ->where('contexts.0.metadata.orchestrator', $orchestrator)
                ->etc();
        });
});

it('REQ-M7-004: rejects metadata.orchestrator larger than 32 KB serialised', function (): void {
    $snapshot = Snapshot::factory()->create();

    // Build a blob whose JSON-encoded length exceeds the 32 KB cap.
    $oversized = ['blob' => str_repeat('A', SnapshotVersioning::ORCHESTRATOR_METADATA_MAX_BYTES + 1024)];

    expect(fn () => SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
        metadata: ['orchestrator' => $oversized],
    ))->toThrow(OrchestratorMetadataTooLargeException::class);

    // No partial revision should have landed.
    expect($snapshot->versions()->count())->toBe(0);

    // Just-at-the-cap blobs are accepted (boundary check).
    $borderline = ['blob' => str_repeat('B', SnapshotVersioning::ORCHESTRATOR_METADATA_MAX_BYTES - 32)];

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
        metadata: ['orchestrator' => $borderline],
    );

    expect($version->revision)->toBe(1);
});

it('REQ-M7-004: accepts arbitrary nested JSON shapes inside metadata.orchestrator', function (): void {
    $snapshot = Snapshot::factory()->create();

    $shapes = [
        // List-shaped root.
        'list' => [
            'orchestrator' => [1, 2, 3, ['nested' => true]],
        ],
        // Deeply nested object.
        'deep' => [
            'orchestrator' => [
                'a' => ['b' => ['c' => ['d' => ['e' => 'leaf']]]],
                'mixed' => [null, false, 0, '', 1.5, 'utf-8 — ✓'],
            ],
        ],
        // Empty object — still accepted.
        'empty' => [
            'orchestrator' => [],
        ],
    ];

    foreach ($shapes as $label => $metadata) {
        $version = SnapshotVersioning::append(
            snapshot: $snapshot,
            viewType: 'table',
            dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
            metadata: $metadata,
        );

        expect($version->metadata['orchestrator'])
            ->toEqual($metadata['orchestrator'], "shape '{$label}' should round-trip verbatim");
    }
});

it('REQ-M7-004: present_structured_data MCP tool persists metadata.orchestrator end-to-end', function (): void {
    $orchestrator = ['scout' => ['ok' => true], 'last_failure' => null];

    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'team-mcp-orch',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id']],
            'rows' => [['id' => 1]],
        ],
        'metadata' => ['orchestrator' => $orchestrator],
    ])->assertOk();

    $snapshot = Snapshot::query()->firstOrFail();

    expect($snapshot->currentVersion->metadata['orchestrator'])->toEqual($orchestrator);
});

<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Policies\SnapshotPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('REQ-M5-005: a transitively-readable snapshot is still write-locked to its workbench owner', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    // Owner builds a report that embeds a table — both live in $owner's workbench.
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'owner-wb']);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl']);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'one']]],
    );

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt']);
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => '# Hi'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    // Owner shares the report with the viewer; REQ-M5-004 grants transitive
    // read on the table.
    $report->setVisibility(SnapshotVisibility::Shared);
    SnapshotShare::query()->create([
        'snapshot_id' => $report->id,
        'email' => strtolower((string) $viewer->email),
        'user_id' => $viewer->id,
        'granted_by_user_id' => $owner->id,
    ]);

    expect(app(SnapshotPolicy::class)->view($viewer->fresh(), $table->fresh()))->toBeTrue();

    // Now the viewer authenticates via Sanctum and tries to write a new
    // version into the owner's workbench through PresentStructuredData.
    // REQ-M4-008 (owner-only writes) still blocks this — transitive READ
    // does not become transitive WRITE.
    Sanctum::actingAs($viewer);

    $arguments = [
        'workbench_slug' => 'owner-wb',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'a', 'label' => 'A']],
            'rows' => [['a' => 'malicious']],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'This workbench is owned by another user; MCP writes are owner-only.',
        ]);

    // No new version was written; the table still has exactly one revision
    // (the owner's original one) and its data_payload is untouched.
    expect(SnapshotVersion::query()->where('snapshot_id', $table->id)->count())->toBe(1);
    expect($table->fresh()->currentVersion->data_payload['rows'][0]['a'])->toBe('one');
});

it('REQ-M5-005: a viewer with a link-share on the report cannot mint a new revision via MCP', function (): void {
    $owner = User::factory()->create();

    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'link-wb']);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl']);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt']);
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [['type' => 'embed', 'snapshot_id' => $table->id]]],
    );

    $report->setVisibility(SnapshotVisibility::Link);

    // Random authenticated stranger tries to write to the table's workbench.
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'link-wb',
        'view_type' => 'report',
        'data_payload' => [
            'blocks' => [['type' => 'markdown', 'body' => '# steal']],
        ],
    ])->assertHasErrors([
        'This workbench is owned by another user; MCP writes are owner-only.',
    ]);

    // Original report revision is still the current one.
    expect($report->fresh()->currentVersion->revision)->toBe(1);
});

<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Nexus\Schemas\ReportViewSchema;
use App\Nexus\Schemas\ReportViewSchemaException;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M5-002: validate() rejects an embed pointing at a snapshot in a different workbench', function (): void {
    $reportWorkbench = Workbench::query()->create(['slug' => 'report-wb', 'name' => 'Reports']);
    $otherWorkbench = Workbench::query()->create(['slug' => 'other-wb', 'name' => 'Other']);

    $foreign = Snapshot::query()->create([
        'workbench_id' => $otherWorkbench->id,
        'slug' => 'foreign-table',
    ]);

    SnapshotVersioning::append(
        snapshot: $foreign,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    expect(fn () => ReportViewSchema::validate([
        'blocks' => [
            ['type' => 'markdown', 'body' => '# Mixed'],
            ['type' => 'embed', 'snapshot_id' => $foreign->id],
        ],
    ], $reportWorkbench->id))->toThrow(
        ReportViewSchemaException::class,
        "data_payload.blocks[1].snapshot_id must reference a snapshot in the same workbench (got snapshot {$foreign->id} from workbench {$otherWorkbench->id}).",
    );
});

it('REQ-M5-002: validate() rejects an embed pointing at a non-existent snapshot', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'report-wb', 'name' => 'Reports']);

    expect(fn () => ReportViewSchema::validate([
        'blocks' => [
            ['type' => 'embed', 'snapshot_id' => 999999],
        ],
    ], $workbench->id))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].snapshot_id references a snapshot that does not exist (id=999999).',
    );
});

it('REQ-M5-002: validate() rejects an embed whose target is itself a report', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'report-wb', 'name' => 'Reports']);

    $nested = Snapshot::query()->create([
        'workbench_id' => $workbench->id,
        'slug' => 'inner-report',
    ]);

    SnapshotVersioning::append(
        snapshot: $nested,
        viewType: 'report',
        dataPayload: ['blocks' => [['type' => 'markdown', 'body' => '# Inner']]],
    );

    expect(fn () => ReportViewSchema::validate([
        'blocks' => [
            ['type' => 'embed', 'snapshot_id' => $nested->id],
        ],
    ], $workbench->id))->toThrow(
        ReportViewSchemaException::class,
        "data_payload.blocks[0].snapshot_id cannot reference another report (nested reports are not allowed; snapshot {$nested->id} has view_type=report).",
    );
});

it('REQ-M5-002: validate() accepts an embed pointing at a same-workbench non-report snapshot', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'report-wb', 'name' => 'Reports']);

    $table = Snapshot::query()->create([
        'workbench_id' => $workbench->id,
        'slug' => 'local-table',
    ]);

    SnapshotVersioning::append(
        snapshot: $table,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '# Hi'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ],
    ];

    expect(ReportViewSchema::validate($payload, $workbench->id))->toBe($payload);
});

it('REQ-M5-002: MCP tool rejects a report embedding a snapshot from another workbench', function (): void {
    $otherWorkbench = Workbench::query()->create(['slug' => 'other-wb', 'name' => 'Other']);

    $foreign = Snapshot::query()->create([
        'workbench_id' => $otherWorkbench->id,
        'slug' => 'foreign-table',
    ]);

    SnapshotVersioning::append(
        snapshot: $foreign,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $arguments = [
        'workbench_slug' => 'reports-x-wb',
        'view_type' => 'report',
        'data_payload' => [
            'blocks' => [
                ['type' => 'embed', 'snapshot_id' => $foreign->id],
            ],
        ],
    ];

    $response = NexusServer::tool(PresentStructuredData::class, $arguments);

    $response->assertHasErrors([
        "data_payload.blocks[0].snapshot_id must reference a snapshot in the same workbench (got snapshot {$foreign->id} from workbench {$otherWorkbench->id}).",
    ]);

    // Cross-workbench rejection should leave no report rows behind — not even
    // the workbench the tool was asked to create.
    expect(SnapshotVersion::query()->where('view_type', 'report')->count())->toBe(0);
    expect(Workbench::query()->where('slug', 'reports-x-wb')->count())->toBe(0);
});

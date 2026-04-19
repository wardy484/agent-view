<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M2-004: accepts a well-formed flowchart payload and persists a snapshot version', function (): void {
    $arguments = [
        'workbench_slug' => 'arch-diagrams',
        'view_type' => 'flowchart',
        'data_payload' => [
            'mermaid_source' => "flowchart TD\nUser --> Proxy --> Service",
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertOk()
        ->assertSee('/workbenches/arch-diagrams/snapshots/');

    expect(Workbench::query()->where('slug', 'arch-diagrams')->exists())->toBeTrue();
    expect(SnapshotVersion::query()->count())->toBe(1);

    $version = SnapshotVersion::query()->first();
    expect($version->view_type)->toBe('flowchart');
    expect($version->preview_html)->toContain('<svg');
    expect($version->preview_html)->toContain('data-nexus-preview="flowchart"');
});

it('REQ-M2-004: rejects a flowchart payload missing mermaid_source and persists nothing', function (): void {
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'arch-diagrams',
        'view_type' => 'flowchart',
        'data_payload' => [],
    ])->assertHasErrors([
        'data_payload.mermaid_source is required and must be a string.',
    ]);

    expect(Workbench::query()->count())->toBe(0);
    expect(Snapshot::query()->count())->toBe(0);
    expect(SnapshotVersion::query()->count())->toBe(0);
});

it('REQ-M2-004: rejects an empty mermaid_source', function (): void {
    NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'arch-diagrams',
        'view_type' => 'flowchart',
        'data_payload' => ['mermaid_source' => '   '],
    ])->assertHasErrors([
        'data_payload.mermaid_source must not be empty.',
    ]);
});

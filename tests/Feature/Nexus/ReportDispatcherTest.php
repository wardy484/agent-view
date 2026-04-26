<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\SnapshotVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M5-000: MCP tool accepts view_type report and persists a version', function (): void {
    $arguments = [
        'workbench_slug' => 'reports-demo',
        'view_type' => 'report',
        'data_payload' => [
            'blocks' => [
                ['type' => 'markdown', 'body' => '# Hello'],
            ],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)->assertOk();

    expect(SnapshotVersion::query()->where('view_type', 'report')->count())->toBe(1);
});

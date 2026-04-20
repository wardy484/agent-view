<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\SnapshotVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

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

it('REQ-M5-000: dashboard exposes a sample slot for the report view_type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('viewTypeSamples.report')
            ->where('viewTypeSamples.report', null));
});

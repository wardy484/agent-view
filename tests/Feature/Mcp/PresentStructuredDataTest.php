<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M1-001: accepts the v1 input schema', function (): void {
    $arguments = [
        'workbench_slug' => 'team-alpha',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'name', 'label' => 'Name'],
            ],
            'rows' => [
                ['id' => 1, 'name' => 'Ada'],
                ['id' => 2, 'name' => 'Linus'],
            ],
        ],
        'snapshot_id' => 'snap_01HZX',
        'title' => 'Engineers',
        'metadata' => ['source' => 'crm'],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertOk();
});

it('REQ-M1-001: accepts the v1 input schema with only required fields', function (): void {
    $arguments = [
        'workbench_slug' => 'team-alpha',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertOk();
});

it('REQ-M1-001: registers present_structured_data with the v1 input schema', function (): void {
    $tool = new PresentStructuredData;
    $array = $tool->toArray();

    expect($tool->name())->toBe('present_structured_data');

    $properties = collect($array['inputSchema']['properties'] ?? [])->keys()->all();
    $required = $array['inputSchema']['required'] ?? [];

    expect($properties)->toEqualCanonicalizing([
        'workbench_slug',
        'view_type',
        'data_payload',
        'snapshot_id',
        'title',
        'metadata',
    ]);

    expect($required)->toEqualCanonicalizing([
        'workbench_slug',
        'view_type',
        'data_payload',
    ]);
});

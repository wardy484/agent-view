<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M1-014: the URL returned by present_structured_data renders the table page', function (): void {
    // REQ-M4-000: MCP calls run as an authenticated Sanctum user; the
    // workbench picks up that caller as its owner, and the snapshot policy
    // (REQ-M4-005) then lets the same user view the URL.
    $caller = User::factory()->create();
    $this->actingAs($caller);

    $rows = [
        ['id' => 1, 'name' => 'Ada Lovelace'],
        ['id' => 2, 'name' => 'Linus Torvalds'],
        ['id' => 3, 'name' => 'Grace Hopper'],
    ];

    $columns = [
        ['key' => 'id', 'label' => 'ID'],
        ['key' => 'name', 'label' => 'Name'],
    ];

    // 1. Run the MCP tool exactly as a client would.
    $toolResponse = NexusServer::tool(PresentStructuredData::class, [
        'workbench_slug' => 'team-alpha',
        'view_type' => 'table',
        'data_payload' => ['columns' => $columns, 'rows' => $rows],
        'title' => 'Engineers',
    ])->assertOk();

    // 2. Pull the URL out of structuredContent.
    $reflector = new ReflectionProperty($toolResponse, 'response');
    $payload = $reflector->getValue($toolResponse)->toArray();
    $url = $payload['result']['structuredContent']['url'] ?? null;

    expect($url)->toBeString()->not->toBe('');

    // 3. GET it and assert the snapshot page renders the Table view with
    //    every row from the original payload.
    $this->withoutVite()
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'table')
            ->where('version.data_payload.columns', $columns)
            ->where('version.data_payload.rows', $rows)
            ->where('snapshot.title', 'Engineers')
        );
});

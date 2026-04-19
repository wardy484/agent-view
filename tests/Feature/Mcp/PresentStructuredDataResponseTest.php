<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('REQ-M1-004: response carries structuredContent, a text URL part, and a text/html resource part', function (): void {
    $arguments = [
        'workbench_slug' => 'team-alpha',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
        'title' => 'Engineers',
    ];

    $response = NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertOk();

    $workbench = Workbench::query()->where('slug', 'team-alpha')->firstOrFail();
    $snapshot = $workbench->snapshots()->latest('id')->firstOrFail();
    $expectedUrl = route('workbench.snapshot.show', [
        'workbench' => $workbench->slug,
        'snapshot' => $snapshot->slug,
    ]);

    // structuredContent — must include the workbench URL and revision metadata.
    $response->assertStructuredContent(function (AssertableJson $json) use ($workbench, $snapshot, $expectedUrl): void {
        $json->where('workbench_slug', $workbench->slug)
            ->where('snapshot_id', $snapshot->id)
            ->where('snapshot_slug', $snapshot->slug)
            ->where('view_type', 'table')
            ->where('revision', 1)
            ->where('url', $expectedUrl)
            ->etc();
    });

    // Text content part with the workbench URL.
    $response->assertSee($expectedUrl);
});

it('REQ-M1-004: emits a text/html resource content part alongside the text part', function (): void {
    $arguments = [
        'workbench_slug' => 'team-bravo',
        'view_type' => 'table',
        'data_payload' => ['columns' => [], 'rows' => []],
    ];

    $tool = new PresentStructuredData;
    $response = NexusServer::tool($tool, $arguments)->assertOk();

    $reflector = new ReflectionProperty($response, 'response');
    $jsonRpc = $reflector->getValue($response);
    $contentParts = $jsonRpc->toArray()['result']['content'] ?? [];

    $textParts = array_values(array_filter($contentParts, fn (array $part) => ($part['type'] ?? null) === 'text'));
    $resourceParts = array_values(array_filter($contentParts, fn (array $part) => ($part['type'] ?? null) === 'resource'));

    expect($textParts)->not->toBeEmpty()
        ->and($resourceParts)->not->toBeEmpty();

    $resource = $resourceParts[0]['resource'] ?? [];
    expect($resource['mimeType'] ?? null)->toBe('text/html')
        ->and($resource['uri'] ?? null)->toBeString()->not->toBe('')
        ->and($resource['text'] ?? null)->toBeString();
});

<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M3-011: ui:// resource carries ?mode=preview on the iframe-embeddable URL', function (): void {
    $arguments = [
        'workbench_slug' => 'team-ui',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ];

    $response = NexusServer::tool(PresentStructuredData::class, $arguments)->assertOk();

    $reflector = new ReflectionProperty($response, 'response');
    $jsonRpc = $reflector->getValue($response);
    $contentParts = $jsonRpc->toArray()['result']['content'] ?? [];

    $uiResources = array_values(array_filter(
        array_filter($contentParts, fn (array $part) => ($part['type'] ?? null) === 'resource'),
        function (array $part): bool {
            $uri = $part['resource']['uri'] ?? '';

            return is_string($uri) && str_starts_with($uri, 'ui://');
        },
    ));

    expect($uiResources)->not->toBeEmpty();

    $iframeUrl = $uiResources[0]['resource']['text'] ?? '';

    expect($iframeUrl)
        ->toBeString()
        ->toMatch('/^https?:\/\//')
        ->toContain('mode=preview');
});

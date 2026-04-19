<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M3-008: response includes a ui:// resource with an iframe-embeddable URL for mcp-ui-aware clients', function (): void {
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

    $resourceParts = array_values(array_filter(
        $contentParts,
        fn (array $part) => ($part['type'] ?? null) === 'resource',
    ));

    $uiResources = array_values(array_filter(
        $resourceParts,
        function (array $part): bool {
            $uri = $part['resource']['uri'] ?? '';

            return is_string($uri) && str_starts_with($uri, 'ui://');
        },
    ));

    expect($uiResources)->not->toBeEmpty();

    $resource = $uiResources[0]['resource'];

    expect($resource['uri'])->toStartWith('ui://');
    // The body should be an iframe-embeddable URL (http[s]://…).
    expect($resource['text'] ?? null)->toBeString();
    expect($resource['text'])->toMatch('/^https?:\/\//');
});

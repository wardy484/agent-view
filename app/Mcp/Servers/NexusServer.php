<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetFollowUpContext;
use App\Mcp\Tools\GetSnapshotComments;
use App\Mcp\Tools\PresentStructuredData;
use App\Mcp\Tools\ResolveComments;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Nexus Server')]
#[Version('0.0.1')]
#[Instructions('Send structured data (tables, kanban boards, flowcharts, slide decks) to a Nexus-UI workbench. Use present_structured_data to publish data and receive a shareable workbench URL.')]
class NexusServer extends Server
{
    protected array $tools = [
        PresentStructuredData::class,
        GetFollowUpContext::class,
        GetSnapshotComments::class,
        ResolveComments::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}

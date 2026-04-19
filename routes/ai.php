<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI / MCP Routes
|--------------------------------------------------------------------------
|
| MCP servers exposed by Nexus-UI. The Sanctum auth requirement called
| out by REQ-M1-002 is layered on the server in a follow-up requirement.
|
*/

Mcp::local('nexus', NexusServer::class);

Mcp::web('/ai/mcp/nexus', NexusServer::class);

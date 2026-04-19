<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI / MCP Routes
|--------------------------------------------------------------------------
|
| Every HTTP route under /ai/* is guarded by Sanctum (REQ-M1-002).
| Anonymous requests receive a 401 response. Personal access tokens are
| minted in /settings/tokens (REQ-M1-013).
|
| Local stdio servers are unauthenticated by design — they run inside the
| operator's shell.
|
*/

Mcp::local('nexus', NexusServer::class);

Route::middleware('auth:sanctum')
    ->prefix('ai')
    ->group(function (): void {
        Mcp::web('/mcp/nexus', NexusServer::class);
    });

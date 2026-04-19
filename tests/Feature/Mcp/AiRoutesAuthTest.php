<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('REQ-M1-002: returns 401 for anonymous POST requests to /ai/* routes', function (): void {
    $this->postJson('/ai/mcp/nexus', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ])->assertStatus(401);
});

it('REQ-M1-002: returns 401 for anonymous GET requests to /ai/* routes', function (): void {
    $this->getJson('/ai/mcp/nexus')->assertStatus(401);
});

it('REQ-M1-002: returns 401 for anonymous DELETE requests to /ai/* routes', function (): void {
    $this->deleteJson('/ai/mcp/nexus')->assertStatus(401);
});

it('REQ-M1-002: every /ai/* route is guarded by the sanctum auth middleware', function (): void {
    $aiRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with(ltrim($route->uri(), '/'), 'ai/'));

    expect($aiRoutes)->not->toBeEmpty();

    $aiRoutes->each(function ($route): void {
        expect($route->gatherMiddleware())
            ->toContain('auth:sanctum');
    });
});

it('REQ-M1-002: accepts a request that carries a valid Sanctum Bearer token', function (): void {
    Sanctum::actingAs(User::factory()->create());

    // Authenticated requests pass the guard. We assert we are not 401 — the
    // body of a POST without a JSON-RPC envelope can still 4xx, but the
    // authentication layer must let us through.
    $response = $this->postJson('/ai/mcp/nexus', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ]);

    expect($response->status())->not->toBe(401);
});

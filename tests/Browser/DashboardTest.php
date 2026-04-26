<?php

declare(strict_types=1);

use App\Models\User;

it('REQ-M11-007: empty dashboard surfaces workbench definition, mint CTA, and MCP endpoint without recents or workbenches list', function (): void {
    // Fresh user with zero workbenches — distinct from the UiBaselineSeeder fixture user.
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('no workbenches yet')
        ->assertSee('A workbench is a project')
        ->assertSee('a named home for the snapshots an')
        ->assertSee('Mint an MCP token')
        ->assertSee('present_structured_data')
        ->assertSee('POST /ai/mcp/nexus')
        ->assertSee('Mint MCP token')
        ->assertDontSee('jump back into your latest snapshots')
        ->assertDontSee('sorted by last activity')
        ->assertNoJavaScriptErrors();
});

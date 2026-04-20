<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Testing\TestResponse;

uses(RefreshDatabase::class);

function mcpPresentCall(array $overrides = []): TestResponse
{
    $defaults = [
        'workbench_slug' => 'team-alpha',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ];

    return NexusServer::tool(PresentStructuredData::class, array_replace($defaults, $overrides));
}

it('REQ-M4-008: the first authenticated caller becomes the workbench owner', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    mcpPresentCall(['workbench_slug' => 'alpha'])->assertOk();

    expect(Workbench::query()->where('slug', 'alpha')->value('owner_user_id'))
        ->toBe($owner->id);
});

it('REQ-M4-008: non-owner authenticated callers cannot write to an owned workbench', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($owner);
    mcpPresentCall(['workbench_slug' => 'alpha'])->assertOk();

    $this->actingAs($intruder);
    $response = mcpPresentCall(['workbench_slug' => 'alpha']);

    // The MCP call must surface an error, and no second snapshot can land.
    $reflector = new ReflectionProperty($response, 'response');
    $payload = $reflector->getValue($response)->toArray();

    expect($payload['result']['isError'] ?? false)->toBeTrue()
        ->and(Workbench::query()->where('slug', 'alpha')->first()->snapshots()->count())->toBe(1);
});

it('REQ-M4-008: shared-with users still cannot write', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();

    $this->actingAs($owner);
    mcpPresentCall(['workbench_slug' => 'alpha'])->assertOk();

    $workbench = Workbench::query()->where('slug', 'alpha')->firstOrFail();
    $snapshot = $workbench->snapshots()->firstOrFail();
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    $this->actingAs($invitee);
    $response = mcpPresentCall(['workbench_slug' => 'alpha']);

    $reflector = new ReflectionProperty($response, 'response');
    $payload = $reflector->getValue($response)->toArray();

    expect($payload['result']['isError'] ?? false)->toBeTrue();
});

it('REQ-M4-008: an authenticated caller cannot take over a system-owned workbench', function (): void {
    // Local-stdio workbenches carry owner_user_id = null. A web Sanctum caller
    // must not hijack such a workbench by writing to it.
    Workbench::factory()->create(['slug' => 'alpha', 'owner_user_id' => null]);

    $this->actingAs(User::factory()->create());
    $response = mcpPresentCall(['workbench_slug' => 'alpha']);

    $reflector = new ReflectionProperty($response, 'response');
    $payload = $reflector->getValue($response)->toArray();

    expect($payload['result']['isError'] ?? false)->toBeTrue()
        ->and(Workbench::query()->where('slug', 'alpha')->value('owner_user_id'))->toBeNull();
});

it('REQ-M4-008: local-stdio (unauthenticated) callers can keep writing to a system-owned workbench', function (): void {
    // This preserves the M1 path for local stdio users — no Auth::id(), but
    // the workbench is system-owned, so there is no owner mismatch.
    Workbench::factory()->create(['slug' => 'alpha', 'owner_user_id' => null]);

    mcpPresentCall(['workbench_slug' => 'alpha'])->assertOk();

    expect(Workbench::query()->where('slug', 'alpha')->first()->snapshots()->count())
        ->toBe(1);
});

it('REQ-M4-008: the owner may append further revisions', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    mcpPresentCall(['workbench_slug' => 'alpha'])->assertOk();
    mcpPresentCall(['workbench_slug' => 'alpha'])->assertOk();

    expect(Workbench::query()->where('slug', 'alpha')->first()->snapshots()->count())
        ->toBe(2);
});

<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\GetFollowUpContext;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\McpCallLog;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M3-006: every MCP call writes a mcp_call_logs row with tool_name, duration_ms, status, payload_bytes', function (): void {
    $arguments = [
        'workbench_slug' => 'team-alpha',
        'view_type' => 'table',
        'data_payload' => [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)->assertOk();

    $log = McpCallLog::query()->latest('id')->firstOrFail();

    expect($log->tool_name)->toBe('present_structured_data')
        ->and($log->status)->toBe('ok')
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($log->payload_bytes)->toBeGreaterThan(0);
});

it('REQ-M3-006: get_follow_up_context calls are also logged', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'log-demo']);

    NexusServer::tool(GetFollowUpContext::class, [
        'workbench_slug' => $workbench->slug,
    ])->assertOk();

    $log = McpCallLog::query()->where('tool_name', 'get_follow_up_context')->latest('id')->firstOrFail();

    expect($log->status)->toBe('ok')
        ->and($log->payload_bytes)->toBeGreaterThan(0);
});

it('REQ-M3-006: failed MCP calls record status=error', function (): void {
    $arguments = [
        'workbench_slug' => 'err-demo',
        'view_type' => 'table',
        // Missing columns — should produce an error response.
        'data_payload' => ['rows' => []],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)->assertHasErrors();

    $log = McpCallLog::query()->latest('id')->firstOrFail();

    expect($log->status)->toBe('error');
});

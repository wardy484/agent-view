<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Models\McpCallLog;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;

/**
 * REQ-M3-007: the "Agent Activity" dashboard is a singleton snapshot per
 * workbench rendered via `snapshot.tsx` — no bespoke page.
 *
 * We build a table payload from the recent `mcp_call_logs` rows and use
 * {@see SnapshotVersioning::append()} to keep the snapshot (slug =
 * `agent-activity`) up to date.
 */
final class AgentActivityDashboard
{
    public const SLUG = 'agent-activity';

    public static function refresh(Workbench $workbench): SnapshotVersion
    {
        $snapshot = Snapshot::query()->firstOrCreate(
            [
                'workbench_id' => $workbench->id,
                'slug' => self::SLUG,
            ],
            [
                'title' => 'Agent Activity',
            ],
        );

        $logs = McpCallLog::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['tool_name', 'status', 'duration_ms', 'payload_bytes', 'created_at']);

        $rows = $logs->map(static fn (McpCallLog $log): array => [
            'tool_name' => $log->tool_name,
            'status' => $log->status,
            'duration_ms' => (int) $log->duration_ms,
            'payload_bytes' => (int) $log->payload_bytes,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->values()->all();

        $payload = [
            'columns' => [
                ['key' => 'tool_name', 'label' => 'Tool'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'duration_ms', 'label' => 'Duration (ms)'],
                ['key' => 'payload_bytes', 'label' => 'Payload (bytes)'],
                ['key' => 'created_at', 'label' => 'At'],
            ],
            'rows' => $rows,
        ];

        return SnapshotVersioning::append(
            snapshot: $snapshot,
            viewType: 'table',
            dataPayload: $payload,
        );
    }
}

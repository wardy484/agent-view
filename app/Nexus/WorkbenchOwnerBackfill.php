<?php

declare(strict_types=1);

namespace App\Nexus;

use Illuminate\Support\Facades\DB;

/**
 * REQ-M4-000: back-fills `workbenches.owner_user_id` from historical
 * `mcp_call_logs`. For every workbench whose owner is null, the earliest
 * `mcp_call_logs` row with a non-null `user_id` wins. Workbenches with
 * no matching log row remain null-owned and are treated as system-owned
 * (never shareable — see REQ-M4-005).
 *
 * Idempotent: re-running the backfill after rows already have an owner
 * is a no-op because the update is scoped with `whereNull('owner_user_id')`.
 */
final class WorkbenchOwnerBackfill
{
    public static function run(): void
    {
        DB::transaction(function (): void {
            $candidates = DB::table('mcp_call_logs')
                ->select('workbench_id', 'user_id')
                ->whereNotNull('workbench_id')
                ->whereNotNull('user_id')
                ->orderBy('workbench_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->unique('workbench_id') // earliest per workbench wins
                ->values();

            foreach ($candidates as $row) {
                DB::table('workbenches')
                    ->where('id', $row->workbench_id)
                    ->whereNull('owner_user_id')
                    ->update(['owner_user_id' => $row->user_id]);
            }
        });
    }
}

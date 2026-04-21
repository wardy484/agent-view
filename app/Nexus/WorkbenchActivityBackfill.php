<?php

declare(strict_types=1);

namespace App\Nexus;

use Illuminate\Support\Facades\DB;

/**
 * REQ-M6-000: back-fills `workbenches.last_activity_at` for every row whose
 * column is null. The value is the maximum `snapshot_versions.created_at`
 * reachable through the workbench's snapshots; workbenches with no versions
 * fall back to `workbenches.created_at` so the dashboard's
 * `last_activity_at desc` sort is well-defined for every row.
 *
 * Idempotent: re-running after the column is populated is a no-op because
 * every update is scoped with `whereNull('last_activity_at')`.
 */
final class WorkbenchActivityBackfill
{
    public static function run(): void
    {
        DB::transaction(function (): void {
            // Latest snapshot version timestamp per workbench.
            DB::statement(<<<'SQL'
                update workbenches w
                set last_activity_at = sub.latest
                from (
                    select s.workbench_id, max(v.created_at) as latest
                    from snapshot_versions v
                    inner join snapshots s on s.id = v.snapshot_id
                    group by s.workbench_id
                ) sub
                where w.id = sub.workbench_id
                  and w.last_activity_at is null
            SQL);

            // Fallback: no versions yet — use the workbench's own created_at.
            DB::statement(<<<'SQL'
                update workbenches
                set last_activity_at = created_at
                where last_activity_at is null
            SQL);
        });
    }
}

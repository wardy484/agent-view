<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Workbench;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * REQ-M6-005: hard-delete soft-deleted workbenches whose `deleted_at`
 * timestamp is older than the retention window (30 days by default).
 * Scheduled in `routes/console.php` to run daily; the cascade configured
 * on `snapshots.workbench_id` (and transitively snapshot_versions /
 * snapshot_shares / snapshot_embeds / snapshot_share_accesses) removes
 * every descendant row at the database level.
 */
#[Signature('workbenches:prune {--days=30 : Retain soft-deleted workbenches for this many days before hard-deleting.}')]
#[Description('Hard-delete workbenches soft-deleted more than --days days ago (cascades to snapshots, versions, shares).')]
class PruneWorkbenches extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $query = Workbench::onlyTrashed()->where('deleted_at', '<', $threshold);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("No workbenches soft-deleted before {$threshold->toDateTimeString()}; nothing to prune.");

            return self::SUCCESS;
        }

        // Iterate per-row so the `forceDelete()` cascade fires normally (a
        // bulk DELETE skips model events and, in some drivers, the ON DELETE
        // CASCADE chain on the FK graph).
        $query->each(function (Workbench $workbench): void {
            $workbench->forceDelete();
        });

        $this->info("Pruned {$count} workbench(es) soft-deleted before {$threshold->toDateTimeString()}.");

        return self::SUCCESS;
    }
}

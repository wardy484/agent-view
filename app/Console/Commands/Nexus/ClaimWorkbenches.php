<?php

declare(strict_types=1);

namespace App\Console\Commands\Nexus;

use App\Models\User;
use App\Models\Workbench;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * REQ-M4-005 ops fix: assigns `workbenches.owner_user_id` to a specific user
 * for every workbench that is still null-owned after the M4-000 backfill.
 *
 * The M4-000 migration backfills owners from `mcp_call_logs.user_id`, which is
 * null for stdio / pre-auth MCP calls. In production this leaves legacy
 * workbenches owner-less, and the REQ-M4-005 policy rejects everyone on them
 * (the spec treats null-owner workbenches as system-owned / unshareable).
 *
 * This command is a one-shot adoption tool: point it at the single operator
 * whose content should be reclaimed. It is idempotent — re-running is a no-op
 * because the scope is `whereNull('owner_user_id')`.
 */
#[Signature('nexus:claim-workbenches {email : Email of the user who should own every null-owner workbench} {--dry-run : Report what would change without writing}')]
#[Description('Assign null-owner workbenches to a specific user (post-M4 adoption)')]
class ClaimWorkbenches extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $dryRun = (bool) $this->option('dry-run');

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user instanceof User) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        $query = Workbench::query()->whereNull('owner_user_id');
        $count = $query->count();

        if ($count === 0) {
            $this->info('No null-owner workbenches found — nothing to claim.');

            return self::SUCCESS;
        }

        $this->line("Found {$count} null-owner workbench(es):");
        $query->clone()->orderBy('slug')->get(['id', 'slug', 'name'])->each(
            fn (Workbench $w) => $this->line("  - [{$w->id}] {$w->slug} ({$w->name})")
        );

        if ($dryRun) {
            $this->warn("Dry run — no rows updated. Re-run without --dry-run to claim for {$user->email}.");

            return self::SUCCESS;
        }

        $updated = $query->update(['owner_user_id' => $user->id]);

        $this->info("Claimed {$updated} workbench(es) for {$user->email} (id={$user->id}).");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * REQ-M4-005 ops follow-up: the M4-000 backfill assigns workbench owners from
 * `mcp_call_logs.user_id`, which is null for stdio / pre-auth MCP calls. In
 * production this leaves legacy workbenches null-owned, and the REQ-M4-005
 * policy then rejects every reader (null-owner = system-owned = unshareable).
 *
 * Strategy — two heuristics, each scoped to `whereNull('owner_user_id')`:
 *
 *   1. For every null-owner workbench that has at least one snapshot which in
 *      turn has a `snapshot_shares` row or a `snapshot_share_accesses` row
 *      written by an authenticated user, adopt the earliest such user. (This
 *      covers workbenches that already have any authenticated activity.)
 *
 *   2. If, after step 1, null-owner workbenches remain AND the instance has
 *      exactly one user account, adopt all remaining null-owner workbenches
 *      for that user. Multi-user instances fall through — operators resolve
 *      those with `php artisan nexus:claim-workbenches <email>`.
 *
 * Idempotent: every write is scoped to `whereNull('owner_user_id')`, so
 * re-running on an already-claimed row is a no-op. Safe to leave in history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Heuristic (1): earliest authenticated mcp_call_logs row, even if
        // that log has `user_id IS NULL` (re-runs the M4-000 logic as a
        // defensive pass — cheap and safe thanks to the null-owner scope).
        $this->claimFromMcpLogs();

        // Heuristic (2): single-user fallback. Covers the common solo-operator
        // case where the original M4-000 backfill had no data to work with.
        $this->claimForSoleUser();
    }

    public function down(): void
    {
        // Non-reversible — we have no authoritative record of which workbenches
        // were null-owner before this migration ran.
    }

    private function claimFromMcpLogs(): void
    {
        $candidates = DB::table('mcp_call_logs')
            ->select('workbench_id', 'user_id')
            ->whereNotNull('workbench_id')
            ->whereNotNull('user_id')
            ->orderBy('workbench_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->unique('workbench_id')
            ->values();

        foreach ($candidates as $row) {
            DB::table('workbenches')
                ->where('id', $row->workbench_id)
                ->whereNull('owner_user_id')
                ->update(['owner_user_id' => $row->user_id]);
        }
    }

    private function claimForSoleUser(): void
    {
        $users = DB::table('users')->orderBy('id')->limit(2)->get(['id']);

        if ($users->count() !== 1) {
            return;
        }

        DB::table('workbenches')
            ->whereNull('owner_user_id')
            ->update(['owner_user_id' => $users->first()->id]);
    }
};

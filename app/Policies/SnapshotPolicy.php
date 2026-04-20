<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * REQ-M4-005: the sole authority on snapshot read access.
 *
 * Grants view when:
 *   1. the user owns the workbench;
 *   2. `visibility = link` and the caller presents the valid `share_token`;
 *   3. `visibility = shared` and an unrevoked `snapshot_shares` row matches
 *      the authenticated user's id OR email (lowercased — stored-form only).
 *
 * A workbench whose `owner_user_id` is null (system-owned / local stdio) is
 * never shareable — every non-owner path is rejected.
 *
 * Write access for MCP tools is a separate concern (REQ-M4-008).
 */
class SnapshotPolicy
{
    /**
     * Re-entry guard for the REQ-M5-004 transitive read check.
     *
     * Reports can never embed reports (REQ-M5-002 enforces it), so a true
     * recursion cycle is impossible — but the guard still short-circuits if
     * a stale pin or future spec change ever creates one.
     *
     * @var array<int, true>
     */
    private static array $transitiveStack = [];

    public function view(?User $user, Snapshot $snapshot, ?string $token = null): bool
    {
        // REQ-M4-005: owner-null workbenches have no one to share on behalf of.
        $ownerId = $snapshot->workbench?->owner_user_id ?? $snapshot->loadMissing('workbench')->workbench?->owner_user_id;

        if ($ownerId === null) {
            return false;
        }

        // (1) Owner always wins.
        if ($user !== null && (int) $user->id === (int) $ownerId) {
            return true;
        }

        // (2) Link visibility + correct token.
        if ($snapshot->visibility === SnapshotVisibility::Link
            && $token !== null
            && $token !== ''
            && hash_equals((string) $snapshot->share_token, $token)
        ) {
            return true;
        }

        // (3) Shared visibility + matching unrevoked share row.
        if ($snapshot->visibility === SnapshotVisibility::Shared && $user !== null) {
            $matched = $snapshot->shares()
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', strtolower((string) $user->email));
                })
                ->exists();

            if ($matched) {
                return true;
            }
        }

        // (4) REQ-M5-004: transitive read via any report whose CURRENT
        // revision still embeds this snapshot. Joining snapshot_embeds to
        // snapshots on report_version_id = snapshots.current_version_id
        // means stale pins (from older report revisions) automatically stop
        // counting once the report is edited — no row deletion needed.
        if ($this->grantedTransitively($user, $snapshot, $token)) {
            return true;
        }

        return false;
    }

    /**
     * REQ-M5-004: returns true when the caller can view some report whose
     * current revision still embeds {@see $snapshot}.
     *
     * Single indexed query → list of report_snapshot_ids → recurse into
     * view() against each. Re-entrancy guard prevents infinite recursion
     * even though REQ-M5-002 already disallows nested reports.
     */
    private function grantedTransitively(?User $user, Snapshot $snapshot, ?string $token): bool
    {
        if (isset(self::$transitiveStack[$snapshot->getKey()])) {
            return false;
        }

        $reportIds = DB::table('snapshot_embeds')
            ->join('snapshots', 'snapshots.current_version_id', '=', 'snapshot_embeds.report_version_id')
            ->where('snapshot_embeds.embedded_snapshot_id', $snapshot->getKey())
            ->where('snapshots.id', '=', DB::raw('snapshot_embeds.report_snapshot_id'))
            ->pluck('snapshots.id')
            ->unique()
            ->all();

        if ($reportIds === []) {
            return false;
        }

        self::$transitiveStack[$snapshot->getKey()] = true;

        try {
            foreach ($reportIds as $reportId) {
                $report = Snapshot::query()->with('workbench')->find($reportId);

                if ($report === null) {
                    continue;
                }

                if ($this->view($user, $report, $token)) {
                    return true;
                }
            }
        } finally {
            unset(self::$transitiveStack[$snapshot->getKey()]);
        }

        return false;
    }
}

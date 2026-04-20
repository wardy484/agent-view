<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\User;

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

        return false;
    }
}

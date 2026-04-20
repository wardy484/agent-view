<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;

/**
 * REQ-M4-007: builds the sidebar data shared on every Inertia response for
 * signed-in users.
 *
 * - `shared_with_me`: snapshots the user can see through an unrevoked
 *   `snapshot_shares` row (resolved by `user_id` OR case-insensitive email).
 * - `owned_badges`: for every snapshot the user owns, the active share count
 *   and whether it has an active link token. The sidebar renders a badge when
 *   either signal is non-zero.
 */
class SidebarSharingData
{
    /**
     * @return array{
     *     shared_with_me: list<array{snapshot_id:int,snapshot_slug:string,snapshot_title:?string,workbench_slug:string,workbench_name:string,url:string}>,
     *     owned_badges: list<array{snapshot_id:int,snapshot_slug:string,workbench_slug:string,share_count:int,has_link:bool}>
     * }
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return ['shared_with_me' => [], 'owned_badges' => []];
        }

        return [
            'shared_with_me' => $this->sharedWithMe($user),
            'owned_badges' => $this->ownedBadges($user),
        ];
    }

    /**
     * @return list<array{snapshot_id:int,snapshot_slug:string,snapshot_title:?string,workbench_slug:string,workbench_name:string,url:string}>
     */
    private function sharedWithMe(User $user): array
    {
        $shares = SnapshotShare::query()
            ->with('snapshot.workbench')
            ->whereNull('revoked_at')
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('email', strtolower((string) $user->email));
            })
            ->get();

        return $shares
            ->map(function (SnapshotShare $share): ?array {
                $snapshot = $share->snapshot;
                if ($snapshot === null || $snapshot->workbench === null) {
                    return null;
                }

                return [
                    'snapshot_id' => $snapshot->id,
                    'snapshot_slug' => $snapshot->slug,
                    'snapshot_title' => $snapshot->title,
                    'workbench_slug' => $snapshot->workbench->slug,
                    'workbench_name' => $snapshot->workbench->name,
                    'url' => route('workbench.snapshot.show', [
                        'workbench' => $snapshot->workbench->slug,
                        'snapshot' => $snapshot->slug,
                    ]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{snapshot_id:int,snapshot_slug:string,workbench_slug:string,share_count:int,has_link:bool}>
     */
    private function ownedBadges(User $user): array
    {
        $snapshots = Snapshot::query()
            ->with('workbench')
            ->whereHas('workbench', fn ($query) => $query->where('owner_user_id', $user->id))
            ->withCount(['allShares as active_share_count' => fn ($query) => $query->whereNull('revoked_at')])
            ->get();

        return $snapshots
            ->map(fn (Snapshot $snapshot): array => [
                'snapshot_id' => $snapshot->id,
                'snapshot_slug' => $snapshot->slug,
                'workbench_slug' => $snapshot->workbench->slug,
                'share_count' => (int) ($snapshot->active_share_count ?? 0),
                'has_link' => $snapshot->visibility === SnapshotVisibility::Link
                    && $snapshot->share_token !== null,
            ])
            ->values()
            ->all();
    }
}

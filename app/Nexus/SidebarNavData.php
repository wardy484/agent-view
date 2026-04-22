<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;

/**
 * Builds the sidebar nav data shared on every Inertia response for
 * signed-in users.
 *
 * - `pinned` / `recent` (REQ-M6-008): owner-scoped workbench shortcuts
 *   populated behind a single eager-loaded query per request.
 * - `shared_with_me` (REQ-M4-007): snapshots the user can see through an
 *   unrevoked `snapshot_shares` row (resolved by `user_id` OR
 *   case-insensitive email).
 * - `owned_badges` (REQ-M4-007): for every snapshot the user owns, the
 *   active share count and whether it has an active link token. The
 *   sidebar renders a badge when either signal is non-zero.
 */
class SidebarNavData
{
    /**
     * @return array{
     *     pinned: list<array{slug:string,name:string,url:string}>,
     *     recent: list<array{slug:string,name:string,url:string}>,
     *     shared_with_me: list<array{snapshot_id:int,snapshot_slug:string,snapshot_title:?string,workbench_slug:string,workbench_name:string,url:string}>,
     *     owned_badges: list<array{snapshot_id:int,snapshot_slug:string,workbench_slug:string,share_count:int,has_link:bool}>
     * }
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [
                'pinned' => [],
                'recent' => [],
                'shared_with_me' => [],
                'owned_badges' => [],
            ];
        }

        [$pinned, $recent] = $this->pinnedAndRecent($user);

        return [
            'pinned' => $pinned,
            'recent' => $recent,
            'shared_with_me' => $this->sharedWithMe($user),
            'owned_badges' => $this->ownedBadges($user),
        ];
    }

    /**
     * REQ-M6-008: single eager-loaded query returns every non-archived
     * workbench owned by the user plus its latest snapshot slug (for the
     * link target). We split in PHP: pinned rows (ordered by
     * `pinned_at desc`) and up to 5 non-pinned rows (ordered by
     * `last_activity_at desc`).
     *
     * @return array{0: list<array{slug:string,name:string,url:string}>, 1: list<array{slug:string,name:string,url:string}>}
     */
    private function pinnedAndRecent(User $user): array
    {
        $workbenches = Workbench::query()
            ->where('owner_user_id', $user->id)
            ->whereNull('archived_at')
            ->with(['snapshots' => fn ($q) => $q->latest('updated_at')->limit(1)])
            ->orderByRaw('pinned_at DESC NULLS LAST')
            ->orderByRaw('last_activity_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->get();

        $pinned = $workbenches
            ->filter(fn (Workbench $w) => $w->pinned_at !== null)
            ->map(fn (Workbench $w) => $this->workbenchLink($w))
            ->values()
            ->all();

        $recent = $workbenches
            ->filter(fn (Workbench $w) => $w->pinned_at === null)
            ->take(5)
            ->map(fn (Workbench $w) => $this->workbenchLink($w))
            ->values()
            ->all();

        return [$pinned, $recent];
    }

    /**
     * @return array{slug:string,name:string,url:string}
     */
    private function workbenchLink(Workbench $workbench): array
    {
        $latest = $workbench->snapshots->first();

        $url = $latest !== null
            ? route('workbench.snapshot.show', [
                'workbench' => $workbench->slug,
                'snapshot' => $latest->slug,
            ])
            : route('workbench.agent-activity', ['workbench' => $workbench->slug]);

        return [
            'slug' => $workbench->slug,
            'name' => $workbench->name,
            'url' => $url,
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

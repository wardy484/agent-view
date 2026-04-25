<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Snapshot;

/**
 * REQ-M6-012: lazy stale resolution. New `SnapshotVersion`s never copy
 * comment rows; instead, each render path consults this service to flip
 * `status` between {@see CommentStatus::Open} and {@see CommentStatus::Stale}
 * based on whether the comment's anchor still resolves in the snapshot's
 * current revision.
 *
 * Terminal states ({@see CommentStatus::Resolved} and
 * {@see CommentStatus::Wontfix}) are never touched — only `resolve_comments`,
 * suggestion-accept (REQ-M6-008), or owner UI action move a comment into or
 * out of those states. `addressed_on_version_id` is also never set here.
 *
 * Auto-revive: if a later revision re-introduces the quoted text, a comment
 * sitting in `stale` is flipped back to `open` automatically — no manual
 * user action is required.
 */
final class CommentStaleUpdater
{
    /**
     * Sync the lazy `open ↔ stale` status of every non-deleted root comment
     * on this snapshot against its current revision. Only persists rows whose
     * status actually changes.
     */
    public static function syncStatusForRender(Snapshot $snapshot): void
    {
        $currentVersion = $snapshot->currentVersion;

        if ($currentVersion === null) {
            return;
        }

        // Single bulk read of every root comment that could be affected. We
        // include `Stale` so re-introduced quotes auto-revive, and `Open` so
        // freshly-broken anchors flip to stale. Terminal states are skipped.
        $candidates = Comment::query()
            ->roots()
            ->where('snapshot_id', $snapshot->id)
            ->whereIn('status', [CommentStatus::Open->value, CommentStatus::Stale->value])
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $toMarkStale = [];
        $toMarkOpen = [];

        foreach ($candidates as $comment) {
            $resolved = AnchorResolver::resolve($comment, $currentVersion);

            if ($resolved->status === 'open' && $comment->status === CommentStatus::Stale) {
                $toMarkOpen[] = $comment->id;
            } elseif ($resolved->status === 'stale' && $comment->status === CommentStatus::Open) {
                $toMarkStale[] = $comment->id;
            }
        }

        if ($toMarkStale !== []) {
            Comment::query()
                ->whereIn('id', $toMarkStale)
                ->update(['status' => CommentStatus::Stale->value]);
        }

        if ($toMarkOpen !== []) {
            Comment::query()
                ->whereIn('id', $toMarkOpen)
                ->update(['status' => CommentStatus::Open->value]);
        }
    }
}

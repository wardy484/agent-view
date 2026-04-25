<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

/**
 * REQ-M6-016: sole writer for `snapshots.comments_revision`. Comment +
 * CommentReaction model events delegate here so every comment, reply,
 * reaction, or resolution write moves the counter forward by exactly one.
 *
 * Implemented as an atomic SQL increment so concurrent writers — say two
 * users reacting at the same time, or an MCP `resolve_comments` overlapping
 * with a UI reply — never lose a bump. Postgres takes the row-level lock
 * implicitly for `UPDATE ... SET col = col + 1`; no application lock needed.
 */
final class CommentsRevisionTracker
{
    /**
     * Bump the counter on a snapshot. Accepts either a hydrated model or a
     * raw id so model-event handlers can call this without an extra fetch.
     */
    public static function bump(Snapshot|int|null $snapshot): void
    {
        $snapshotId = $snapshot instanceof Snapshot
            ? (int) $snapshot->getKey()
            : ($snapshot === null ? null : (int) $snapshot);

        if ($snapshotId === null || $snapshotId <= 0) {
            return;
        }

        DB::table('snapshots')
            ->where('id', $snapshotId)
            ->update(['comments_revision' => DB::raw('comments_revision + 1')]);
    }
}

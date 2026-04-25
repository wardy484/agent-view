<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Models\SnapshotVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * REQ-M6-025: data-only back-fill that assigns UUID v4 ids to every block in
 * the `data_payload.blocks` array of historical report snapshot_versions
 * rows that were created before REQ-M6-001 shipped.
 *
 * Without ids on those blocks the frontend's `data-comment-block-id`
 * attribute is omitted, the floating selection pill never mounts, and
 * comments are unusable on those snapshots. This back-fill repairs the
 * historical payload in-place — it is the documented exception to the
 * "SnapshotVersioning::append() is the sole writer" rule, mirroring the
 * pattern established by REQ-M6-002's `snapshot_embeds.block_id` back-fill.
 *
 * Idempotent: a second run finds every block already has an id and skips
 * every row.
 */
final class BackfillBlockIds
{
    /**
     * Walk every report SnapshotVersion and assign UUIDs to blocks lacking one.
     *
     * @return int the number of rows whose data_payload was rewritten.
     */
    public static function run(): int
    {
        $updated = 0;

        DB::transaction(function () use (&$updated): void {
            SnapshotVersion::query()
                ->where('view_type', 'report')
                ->orderBy('id')
                ->lazy()
                ->each(function (SnapshotVersion $version) use (&$updated): void {
                    $payload = $version->data_payload;

                    if (! is_array($payload)) {
                        return;
                    }

                    $blocks = $payload['blocks'] ?? null;

                    if (! is_array($blocks) || $blocks === []) {
                        return;
                    }

                    $blocks = array_values($blocks);
                    $changed = false;

                    foreach ($blocks as $index => $block) {
                        if (! is_array($block)) {
                            continue;
                        }

                        $existingId = $block['id'] ?? null;

                        if (is_string($existingId) && Str::isUuid($existingId)) {
                            continue;
                        }

                        $block['id'] = (string) Str::uuid();
                        $blocks[$index] = $block;
                        $changed = true;
                    }

                    if (! $changed) {
                        return;
                    }

                    $payload['blocks'] = $blocks;

                    SnapshotVersion::query()
                        ->where('id', $version->id)
                        ->update(['data_payload' => json_encode($payload)]);

                    $updated++;
                });
        });

        return $updated;
    }
}

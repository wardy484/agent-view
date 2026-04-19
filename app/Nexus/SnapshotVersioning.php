<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use Illuminate\Support\Facades\DB;

/**
 * Sole authorised writer for `snapshot_versions`.
 *
 * REQ-M1-003: revisions are monotonic (1, 2, 3, …) and unique per snapshot.
 * REQ-M1-009 (forthcoming) will pin this class as the only legal writer.
 */
class SnapshotVersioning
{
    /**
     * Append a new revision to a snapshot.
     *
     * Locks the snapshot row for the duration of the transaction so two
     * concurrent appends cannot race for the same revision number. The
     * UNIQUE(snapshot_id, revision) index is the belt-and-braces guarantee.
     *
     * @param  array<string, mixed>  $dataPayload
     * @param  array<string, mixed>|null  $metadata
     */
    public static function append(
        Snapshot $snapshot,
        string $viewType,
        array $dataPayload,
        ?array $metadata = null,
        ?string $previewHtml = null,
    ): SnapshotVersion {
        return DB::transaction(function () use ($snapshot, $viewType, $dataPayload, $metadata, $previewHtml): SnapshotVersion {
            // Pessimistic lock — blocks any other append() against this snapshot.
            Snapshot::query()->whereKey($snapshot->getKey())->lockForUpdate()->first();

            $nextRevision = (int) SnapshotVersion::query()
                ->where('snapshot_id', $snapshot->getKey())
                ->max('revision') + 1;

            return SnapshotVersion::query()->create([
                'snapshot_id' => $snapshot->getKey(),
                'revision' => $nextRevision,
                'view_type' => $viewType,
                'data_payload' => $dataPayload,
                'metadata' => $metadata,
                'preview_html' => $previewHtml,
            ]);
        });
    }
}

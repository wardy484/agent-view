<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sole authorised writer for `snapshot_versions`.
 *
 * REQ-M1-003: revisions are monotonic (1, 2, 3, …) and unique per snapshot.
 * REQ-M1-009: this class is the *only* legal writer. The SnapshotVersion
 * model's `saving` hook (registered in AppServiceProvider) consults
 * {@see self::isWriting()} and throws when called from anywhere else.
 */
class SnapshotVersioning
{
    private static bool $writing = false;

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
        return self::runAuthorised(fn (): SnapshotVersion => DB::transaction(function () use ($snapshot, $viewType, $dataPayload, $metadata, $previewHtml): SnapshotVersion {
            // Pessimistic lock — blocks any other append() against this snapshot.
            Snapshot::query()->whereKey($snapshot->getKey())->lockForUpdate()->first();

            $nextRevision = (int) SnapshotVersion::query()
                ->where('snapshot_id', $snapshot->getKey())
                ->max('revision') + 1;

            $version = SnapshotVersion::query()->create([
                'snapshot_id' => $snapshot->getKey(),
                'revision' => $nextRevision,
                'view_type' => $viewType,
                'data_payload' => $dataPayload,
                'metadata' => $metadata,
                'preview_html' => $previewHtml,
            ]);

            // REQ-M1-010: every append updates current_version_id to the
            // newly-written row so readers always see the latest revision.
            Snapshot::query()
                ->whereKey($snapshot->getKey())
                ->update(['current_version_id' => $version->id]);

            $snapshot->forceFill(['current_version_id' => $version->id])->syncOriginalAttribute('current_version_id');

            return $version;
        }));
    }

    /**
     * Returns true while a SnapshotVersioning method is mid-write. The model
     * guard reads this flag.
     */
    public static function isWriting(): bool
    {
        return self::$writing;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private static function runAuthorised(callable $callback): mixed
    {
        $previous = self::$writing;
        self::$writing = true;

        try {
            return $callback();
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            self::$writing = $previous;
        }
    }
}

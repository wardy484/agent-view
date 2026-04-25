<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Events\SnapshotVersionAppended;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Nexus\Renderers\FlowchartPreviewRenderer;
use App\Nexus\Renderers\KanbanPreviewRenderer;
use App\Nexus\Renderers\ReportPreviewRenderer;
use App\Nexus\Renderers\SlideDeckPreviewRenderer;
use App\Nexus\Renderers\TablePreviewRenderer;
use App\Nexus\Schemas\ReportViewSchema;
use Carbon\CarbonImmutable;
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
    /**
     * REQ-M7-004: maximum JSON-serialised size of `metadata.orchestrator`.
     * 32 KB is plenty for scout outputs / dependency DAG / subagent IDs and
     * keeps the row from ballooning the `metadata` JSONB column.
     */
    public const ORCHESTRATOR_METADATA_MAX_BYTES = 32 * 1024;

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
        // REQ-M7-004: enforce the 32 KB cap on `metadata.orchestrator` before
        // any DB work so an oversized blob never lands a partial revision.
        // The blob's inner shape is free-form — the orchestrator owns its own
        // contract — so we only validate (a) it's a JSON-serialisable
        // array/object and (b) its serialised length is within the cap.
        if ($metadata !== null && array_key_exists('orchestrator', $metadata)) {
            self::guardOrchestratorMetadata($metadata['orchestrator']);
        }

        // REQ-M6-001: for report payloads, normalise block ids by carrying
        // forward from the previous version's blocks where bodies match by
        // sha1. The MCP-layer call to ReportViewSchema::validate() runs
        // structural checks but doesn't have the previous version handy;
        // here we have, so we re-run validation with the carry-forward arg.
        if ($viewType === 'report') {
            $previousBlocks = self::previousReportBlocks($snapshot);
            $dataPayload = ReportViewSchema::validate(
                $dataPayload,
                $snapshot->workbench_id,
                $previousBlocks,
            );
        }

        // REQ-M1-012: render the preview at write-time so reads never re-render.
        $previewHtml ??= self::renderPreview($viewType, $dataPayload);

        $version = self::runAuthorised(fn (): SnapshotVersion => DB::transaction(function () use ($snapshot, $viewType, $dataPayload, $metadata, $previewHtml): SnapshotVersion {
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

            // REQ-M5-003: pin every embed in a report payload to the
            // embedded snapshot's current_version_id at write-time. Pins are
            // immutable for this revision; the next report revision re-pins.
            if ($viewType === 'report') {
                self::pinEmbeds($version, $dataPayload);
            }

            return $version;
        }));

        // REQ-M7-002: broadcast AFTER the transaction commits and
        // current_version_id has been bumped. Firing post-commit avoids
        // notifying subscribers about a revision that may roll back, and
        // guarantees the snapshot row is consistent with the broadcast
        // payload before any subscriber re-fetches.
        broadcast(new SnapshotVersionAppended($version));

        return $version;
    }

    /**
     * REQ-M5-003: materialise one `snapshot_embeds` row per embed block.
     *
     * Reads each embedded snapshot's current_version_id INSIDE the same
     * transaction (and after the report version's own row is written) so a
     * concurrent append on the embedded snapshot cannot leave the pin
     * pointing at a half-written version.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function pinEmbeds(SnapshotVersion $reportVersion, array $payload): void
    {
        $blocks = $payload['blocks'] ?? [];

        if (! is_array($blocks) || $blocks === []) {
            return;
        }

        $rows = [];
        $now = CarbonImmutable::now();

        foreach (array_values($blocks) as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'embed') {
                continue;
            }

            $embeddedSnapshotId = $block['snapshot_id'] ?? null;

            if (! is_int($embeddedSnapshotId)) {
                continue;
            }

            // REQ-M6-002: pins are keyed by the block's stable UUID id, not
            // its positional index. ReportViewSchema::validate() has already
            // populated `id` (carried forward or freshly minted) for every
            // block before SnapshotVersioning::append() reaches this point.
            $blockId = $block['id'] ?? null;

            if (! is_string($blockId) || $blockId === '') {
                continue;
            }

            // ReportViewSchema::validate() has already proven the snapshot
            // exists and lives in the same workbench, so this lookup is
            // load-only — never throws.
            $embedded = Snapshot::query()
                ->whereKey($embeddedSnapshotId)
                ->lockForUpdate()
                ->first(['id', 'current_version_id']);

            if ($embedded === null || $embedded->current_version_id === null) {
                continue;
            }

            $rows[] = [
                'report_snapshot_id' => $reportVersion->snapshot_id,
                'report_version_id' => $reportVersion->id,
                'embedded_snapshot_id' => (int) $embedded->id,
                'embedded_version_id' => (int) $embedded->current_version_id,
                'block_id' => $blockId,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('snapshot_embeds')->insert($rows);
        }
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
     * REQ-M6-001: load the previous version's report `blocks` so the
     * validator can carry block ids forward by content fingerprint. Returns
     * null on first revision (or when there is nothing usable to carry).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private static function previousReportBlocks(Snapshot $snapshot): ?array
    {
        $currentVersionId = $snapshot->current_version_id;

        if ($currentVersionId === null) {
            return null;
        }

        $previous = SnapshotVersion::query()
            ->whereKey($currentVersionId)
            ->first(['id', 'view_type', 'data_payload']);

        if ($previous === null || $previous->view_type !== 'report') {
            return null;
        }

        $blocks = $previous->data_payload['blocks'] ?? null;

        return is_array($blocks) ? $blocks : null;
    }

    /**
     * Render the cached preview HTML for a view type. Returns null when no
     * renderer ships for that view (the caller may pass an explicit preview).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function renderPreview(string $viewType, array $payload): ?string
    {
        return match ($viewType) {
            'table' => TablePreviewRenderer::render($payload),
            'slide_deck' => SlideDeckPreviewRenderer::render($payload),
            'kanban' => KanbanPreviewRenderer::render($payload),
            'flowchart' => FlowchartPreviewRenderer::render($payload),
            'report' => ReportPreviewRenderer::render($payload),
            default => null,
        };
    }

    /**
     * REQ-M7-004: validate the `metadata.orchestrator` blob.
     *
     * The blob is intentionally free-form — anything that survives a
     * JSON encode round-trip is acceptable shape-wise. The only hard rule
     * is the 32 KB serialised cap; we throw {@see OrchestratorMetadataTooLargeException}
     * on overflow rather than silently truncating so the orchestrator is
     * forced to summarise / spill state itself.
     */
    private static function guardOrchestratorMetadata(mixed $orchestrator): void
    {
        $encoded = json_encode($orchestrator, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new OrchestratorMetadataTooLargeException(
                'metadata.orchestrator must be JSON-serialisable: '.json_last_error_msg()
            );
        }

        $bytes = strlen($encoded);

        if ($bytes > self::ORCHESTRATOR_METADATA_MAX_BYTES) {
            throw new OrchestratorMetadataTooLargeException(sprintf(
                'metadata.orchestrator exceeds the %d-byte cap (got %d bytes serialised).',
                self::ORCHESTRATOR_METADATA_MAX_BYTES,
                $bytes,
            ));
        }
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

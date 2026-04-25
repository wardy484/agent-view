<?php

declare(strict_types=1);

namespace App\Nexus;

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

            // REQ-M5-003: pin every embed in a report payload to the
            // embedded snapshot's current_version_id at write-time. Pins are
            // immutable for this revision; the next report revision re-pins.
            if ($viewType === 'report') {
                self::pinEmbeds($version, $dataPayload);
            }

            return $version;
        }));
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

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'embed') {
                continue;
            }

            $embeddedSnapshotId = $block['snapshot_id'] ?? null;

            if (! is_int($embeddedSnapshotId)) {
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
                'block_index' => $index,
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

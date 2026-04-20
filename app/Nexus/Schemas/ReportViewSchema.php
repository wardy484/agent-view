<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use App\Models\Snapshot;

/**
 * REQ-M5-001: structural validator for the Report view's `data_payload`.
 *
 * The v1 Report schema requires:
 *  - `blocks` — non-empty ordered array of block objects.
 *  - each block has a `type` of either `markdown` or `embed` (discriminated
 *    union):
 *    - `markdown` blocks require a string `body`.
 *    - `embed` blocks require an int `snapshot_id`.
 *
 * REQ-M5-002: when the optional `$workbenchId` argument is supplied, embeds
 * are additionally validated against the database:
 *  - the referenced snapshot must exist;
 *  - the referenced snapshot's `workbench_id` must equal `$workbenchId`
 *    (no cross-workbench embeds for M5);
 *  - the referenced snapshot's current `view_type` must NOT be `report`
 *    (no nested reports).
 *
 * Passing `$workbenchId = null` means "the report is being written into a
 * brand-new workbench"; since brand-new workbenches contain no snapshots,
 * any embed in that case is cross-workbench by definition and is rejected
 * with the same dot-path error.
 *
 * Validation throws {@see ReportViewSchemaException} with a clear,
 * dot-path message pointing at the first offending field.
 */
final class ReportViewSchema
{
    private const ALLOWED_TYPES = ['markdown', 'embed'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ReportViewSchemaException
     */
    public static function validate(array $payload, ?int $workbenchId = null): array
    {
        $blocks = $payload['blocks'] ?? null;

        if (! is_array($blocks) || $blocks === []) {
            throw new ReportViewSchemaException(
                'data_payload.blocks is required and must be a non-empty array.',
            );
        }

        $embedIds = [];

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block)) {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}] must be an object.",
                );
            }

            $type = $block['type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true)) {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}].type is required and must be one of: markdown, embed.",
                );
            }

            if ($type === 'markdown') {
                if (! isset($block['body']) || ! is_string($block['body'])) {
                    throw new ReportViewSchemaException(
                        "data_payload.blocks[{$index}].body is required and must be a string.",
                    );
                }

                continue;
            }

            // type === 'embed'
            if (! isset($block['snapshot_id']) || ! is_int($block['snapshot_id'])) {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}].snapshot_id is required and must be an int.",
                );
            }

            $embedIds[$index] = $block['snapshot_id'];
        }

        if ($embedIds !== []) {
            self::validateEmbeds($embedIds, $workbenchId);
        }

        return $payload;
    }

    /**
     * REQ-M5-002: cross-workbench + nested-report rejection.
     *
     * Loaded in a single query with eager-loaded `currentVersion` so the
     * view_type check costs no extra round-trips even with many embeds.
     *
     * @param  array<int, int>  $embedIds  block-index => snapshot_id
     *
     * @throws ReportViewSchemaException
     */
    private static function validateEmbeds(array $embedIds, ?int $workbenchId): void
    {
        $snapshots = Snapshot::query()
            ->whereIn('id', array_values($embedIds))
            ->with('currentVersion:id,view_type')
            ->get(['id', 'workbench_id', 'current_version_id'])
            ->keyBy('id');

        foreach ($embedIds as $index => $snapshotId) {
            $snapshot = $snapshots->get($snapshotId);

            if ($snapshot === null) {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}].snapshot_id references a snapshot that does not exist (id={$snapshotId}).",
                );
            }

            if ((int) $snapshot->workbench_id !== (int) $workbenchId) {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}].snapshot_id must reference a snapshot in the same workbench (got snapshot {$snapshotId} from workbench {$snapshot->workbench_id}).",
                );
            }

            $embeddedViewType = $snapshot->currentVersion?->view_type;

            if ($embeddedViewType === 'report') {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}].snapshot_id cannot reference another report (nested reports are not allowed; snapshot {$snapshotId} has view_type=report).",
                );
            }
        }
    }
}

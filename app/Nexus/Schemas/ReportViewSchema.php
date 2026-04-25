<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use App\Models\Snapshot;
use Illuminate\Support\Str;

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
 * REQ-M6-001: each block carries a stable `id` field (UUID v4). Callers
 * may supply explicit ids (validated as UUID strings) or omit them. When
 * `$previousBlocks` is supplied, missing ids are best-effort carried
 * forward from the previous version's block whose body (markdown) or
 * snapshot_id (embed) sha1-matches; otherwise a fresh UUID is generated.
 *
 * Validation throws {@see ReportViewSchemaException} with a clear,
 * dot-path message pointing at the first offending field.
 */
final class ReportViewSchema
{
    private const ALLOWED_TYPES = ['markdown', 'embed'];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>|null  $previousBlocks
     * @return array<string, mixed>
     *
     * @throws ReportViewSchemaException
     */
    public static function validate(array $payload, ?int $workbenchId = null, ?array $previousBlocks = null): array
    {
        $blocks = $payload['blocks'] ?? null;

        if (! is_array($blocks) || $blocks === []) {
            throw new ReportViewSchemaException(
                'data_payload.blocks is required and must be a non-empty array.',
            );
        }

        $embedIds = [];
        $normalisedBlocks = [];
        $carryIndex = self::buildCarryIndex($previousBlocks);

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
            } else {
                // type === 'embed'
                if (! isset($block['snapshot_id']) || ! is_int($block['snapshot_id'])) {
                    throw new ReportViewSchemaException(
                        "data_payload.blocks[{$index}].snapshot_id is required and must be an int.",
                    );
                }

                $embedIds[$index] = $block['snapshot_id'];
            }

            $block['id'] = self::resolveBlockId($block, $index, $carryIndex);

            $normalisedBlocks[] = $block;
        }

        if ($embedIds !== []) {
            self::validateEmbeds($embedIds, $workbenchId);
        }

        $payload['blocks'] = $normalisedBlocks;

        return $payload;
    }

    /**
     * Resolve the `id` for a single block:
     *  - explicit UUID supplied: validate and return.
     *  - explicit non-UUID supplied: throw.
     *  - missing: try carry-forward from previous version, else fresh UUID.
     *
     * @param  array<string, mixed>  $block
     * @param  array{markdown: array<string, string>, embed: array<int, string>}  $carryIndex
     *
     * @throws ReportViewSchemaException
     */
    private static function resolveBlockId(array $block, int $index, array $carryIndex): string
    {
        if (array_key_exists('id', $block)) {
            $id = $block['id'];

            if (! is_string($id) || ! Str::isUuid($id)) {
                throw new ReportViewSchemaException(
                    "data_payload.blocks[{$index}].id must be a UUID string when supplied.",
                );
            }

            return $id;
        }

        if ($block['type'] === 'markdown') {
            $fingerprint = sha1($block['body']);
            $carried = $carryIndex['markdown'][$fingerprint] ?? null;

            if ($carried !== null) {
                return $carried;
            }
        } elseif ($block['type'] === 'embed') {
            $snapshotId = (int) $block['snapshot_id'];
            $carried = $carryIndex['embed'][$snapshotId] ?? null;

            if ($carried !== null) {
                return $carried;
            }
        }

        return Str::uuid()->toString();
    }

    /**
     * Build a lookup of {fingerprint -> block_id} from the previous version's
     * blocks so we can carry ids forward by content match.
     *
     * @param  array<int, array<string, mixed>>|null  $previousBlocks
     * @return array{markdown: array<string, string>, embed: array<int, string>}
     */
    private static function buildCarryIndex(?array $previousBlocks): array
    {
        $index = ['markdown' => [], 'embed' => []];

        if ($previousBlocks === null) {
            return $index;
        }

        foreach ($previousBlocks as $previous) {
            if (! is_array($previous)) {
                continue;
            }

            $previousId = $previous['id'] ?? null;
            $previousType = $previous['type'] ?? null;

            if (! is_string($previousId) || ! Str::isUuid($previousId)) {
                continue;
            }

            if ($previousType === 'markdown' && isset($previous['body']) && is_string($previous['body'])) {
                $fingerprint = sha1($previous['body']);
                // First match wins so duplicate bodies don't shuffle ids around.
                if (! isset($index['markdown'][$fingerprint])) {
                    $index['markdown'][$fingerprint] = $previousId;
                }
            } elseif ($previousType === 'embed' && isset($previous['snapshot_id']) && is_int($previous['snapshot_id'])) {
                if (! isset($index['embed'][$previous['snapshot_id']])) {
                    $index['embed'][$previous['snapshot_id']] = $previousId;
                }
            }
        }

        return $index;
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

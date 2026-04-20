<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

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
 * Cross-workbench and no-nested-report rejection live in REQ-M5-002 and are
 * enforced by the MCP tool path (which knows the active workbench) — this
 * schema is a pure structural validator.
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
    public static function validate(array $payload): array
    {
        $blocks = $payload['blocks'] ?? null;

        if (! is_array($blocks) || $blocks === []) {
            throw new ReportViewSchemaException(
                'data_payload.blocks is required and must be a non-empty array.',
            );
        }

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
        }

        return $payload;
    }
}

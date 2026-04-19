<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use App\Nexus\Renderers\FlowchartPreviewRenderer;

/**
 * REQ-M2-004: structural validator for the Flowchart view's `data_payload`.
 *
 * The v1 Flowchart schema requires:
 *  - `mermaid_source` — non-empty string containing a Mermaid diagram
 *    definition (e.g. `flowchart TD; A --> B`). The source is not parsed
 *    or executed server-side here; structural checks only. Mermaid.js is
 *    responsible for rendering in the browser (REQ-M2-005) and
 *    {@see FlowchartPreviewRenderer} produces the
 *    server-side SVG fallback for previews (REQ-M2-006).
 *
 * Validation throws {@see FlowchartViewSchemaException} with a clear
 * dot-path message pointing at the first offending field.
 */
final class FlowchartViewSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws FlowchartViewSchemaException
     */
    public static function validate(array $payload): array
    {
        if (! array_key_exists('mermaid_source', $payload) || ! is_string($payload['mermaid_source'])) {
            throw new FlowchartViewSchemaException(
                'data_payload.mermaid_source is required and must be a string.',
            );
        }

        if (trim($payload['mermaid_source']) === '') {
            throw new FlowchartViewSchemaException(
                'data_payload.mermaid_source must not be empty.',
            );
        }

        return $payload;
    }
}

<?php

declare(strict_types=1);

namespace App\Nexus\Renderers;

/**
 * REQ-M2-006 / REQ-M1-011: server-side SVG preview of a Flowchart view
 * payload.
 *
 *  - Emits an SVG document whose body renders the raw Mermaid source as
 *    escaped text wrapped inside a <foreignObject>. Clients that cannot
 *    execute Mermaid.js (email agents, static renders, diff viewers) still
 *    see the diagram source at a glance.
 *  - Interactive rendering is the browser's job via Mermaid.js
 *    (REQ-M2-005); this preview is the server-side fallback.
 *  - Hard-caps output at 64 KB by truncating the Mermaid source if it
 *    would otherwise blow the budget.
 */
final class FlowchartPreviewRenderer
{
    public const BYTE_LIMIT = 64 * 1024;

    /**
     * @param  array<string, mixed>  $payload  expected shape: { mermaid_source: string }
     */
    public static function render(array $payload): string
    {
        $source = is_string($payload['mermaid_source'] ?? null) ? $payload['mermaid_source'] : '';
        $originalBytes = strlen($source);
        $truncated = false;

        $html = self::build($source, $originalBytes, $truncated);

        while (strlen($html) > self::BYTE_LIMIT && $source !== '') {
            // Trim ~5% off the tail each loop until we fit.
            $source = substr($source, 0, max(0, (int) (strlen($source) * 0.95) - 1));
            $truncated = true;
            $html = self::build($source, $originalBytes, $truncated);
        }

        return $html;
    }

    private static function build(string $source, int $originalBytes, bool $truncated): string
    {
        $escaped = e($source);
        $footer = $truncated
            ? '<footer data-truncated="true">Source truncated ('.$originalBytes.' bytes original).</footer>'
            : '';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 480" data-nexus-preview="flowchart" data-source-bytes="'.$originalBytes.'">'
            .'<foreignObject x="0" y="0" width="800" height="480">'
            .'<div xmlns="http://www.w3.org/1999/xhtml" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;line-height:1.4;padding:16px;white-space:pre-wrap;word-break:break-word;">'
            .$escaped
            .'</div>'
            .'</foreignObject>'
            .'</svg>';

        return '<div data-nexus-preview-wrap="flowchart">'.$svg.$footer.'</div>';
    }
}

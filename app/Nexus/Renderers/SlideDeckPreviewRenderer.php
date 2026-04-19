<?php

declare(strict_types=1);

namespace App\Nexus\Renderers;

/**
 * REQ-M3-001 / REQ-M1-011: server-rendered HTML preview of a Slide Deck
 * view payload.
 *
 *  - Hard-caps output at 64 KB (drops slides from the tail if the budget is
 *    blown).
 *  - All text is HTML-escaped — payloads come from arbitrary MCP clients.
 *  - body_md is rendered as escaped text inside a <pre> — this is a preview,
 *    not a markdown engine.
 */
final class SlideDeckPreviewRenderer
{
    public const BYTE_LIMIT = 64 * 1024;

    /**
     * @param  array<string, mixed>  $payload  expected shape: { slides: [{title, body_md}] }
     */
    public static function render(array $payload): string
    {
        $slides = is_array($payload['slides'] ?? null) ? array_values($payload['slides']) : [];
        $totalSlides = count($slides);
        $visible = $slides;

        $html = self::build($visible, $totalSlides, shownCount: count($visible));

        while (strlen($html) > self::BYTE_LIMIT && $visible !== []) {
            array_pop($visible);
            $html = self::build($visible, $totalSlides, shownCount: count($visible));
        }

        return $html;
    }

    /**
     * @param  list<array<string, mixed>>  $visibleSlides
     */
    private static function build(array $visibleSlides, int $totalSlides, int $shownCount): string
    {
        $sections = '';
        foreach ($visibleSlides as $index => $slide) {
            $title = is_string($slide['title'] ?? null) ? $slide['title'] : '';
            $body = is_string($slide['body_md'] ?? null) ? $slide['body_md'] : '';
            $sections .= '<section data-slide-index="'.$index.'">'
                .'<h2>'.e($title).'</h2>'
                .'<pre>'.e($body).'</pre>'
                .'</section>';
        }

        $footer = $shownCount < $totalSlides
            ? '<footer data-truncated="true">Showing '.$shownCount.' of '.$totalSlides.'</footer>'
            : '';

        return '<div data-nexus-preview="slide_deck" data-slide-count="'.$totalSlides.'">'.$sections.$footer.'</div>';
    }
}

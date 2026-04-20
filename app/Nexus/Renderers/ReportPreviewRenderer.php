<?php

declare(strict_types=1);

namespace App\Nexus\Renderers;

use App\Models\Snapshot;
use Illuminate\Support\Collection;

/**
 * REQ-M5-006: server-rendered HTML preview of a Report view payload.
 *
 *  - Renders each block in order: markdown blocks as escaped <pre>, embed
 *    blocks as compact summary cards (title + view_type badge + a slice
 *    of the embedded preview).
 *  - Hard-caps output at 64 KB. Embed bodies share an even byte budget
 *    derived from `(BYTE_LIMIT - markdown_bytes) / count(embeds)`.
 *  - All text is HTML-escaped.
 *  - Embed targets are looked up by `snapshot_id`. Missing or already-deleted
 *    targets degrade to a `data-restricted="true"` placeholder card.
 *
 * The renderer is best-effort and read-only: the canonical pin (which
 * snapshot_version was inlined into THIS report revision) lives in the
 * `snapshot_embeds` denorm table and is consulted by the controller at
 * read-time (REQ-M5-007).
 */
final class ReportPreviewRenderer
{
    public const BYTE_LIMIT = 64 * 1024;

    /**
     * Hard floor for per-embed body bytes — even when math says less, give
     * each card enough room for the title + badge + a tiny preview snippet.
     */
    private const MIN_EMBED_BYTES = 256;

    /**
     * @param  array<string, mixed>  $payload  expected shape: { blocks: [...] }
     */
    public static function render(array $payload): string
    {
        $blocks = is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : [];
        $totalBlocks = count($blocks);

        if ($blocks === []) {
            return '<div data-nexus-preview="report" data-block-count="0"></div>';
        }

        $embedSnapshots = self::loadEmbeddedSnapshots($blocks);
        $markdownBytes = self::estimateMarkdownBytes($blocks);
        $embedCount = self::countEmbeds($blocks);
        $embedBudget = $embedCount > 0
            ? max(self::MIN_EMBED_BYTES, intdiv(max(0, self::BYTE_LIMIT - $markdownBytes - 512), $embedCount))
            : self::BYTE_LIMIT;

        $visible = $blocks;
        $html = self::build($visible, $totalBlocks, count($visible), $embedSnapshots, $embedBudget);

        // Belt-and-braces: if we're over the cap, drop blocks in batches
        // (binary-search-style halving) to bound this at O(log N) rebuilds
        // even on pathological 5_000-block payloads.
        if (strlen($html) > self::BYTE_LIMIT) {
            $low = 0;
            $high = count($visible);

            while ($low < $high) {
                $mid = intdiv($low + $high + 1, 2);
                $candidate = array_slice($visible, 0, $mid);
                $candidateHtml = self::build($candidate, $totalBlocks, $mid, $embedSnapshots, $embedBudget);

                if (strlen($candidateHtml) <= self::BYTE_LIMIT) {
                    $low = $mid;
                } else {
                    $high = $mid - 1;
                }
            }

            $visible = array_slice($visible, 0, $low);
            $html = self::build($visible, $totalBlocks, count($visible), $embedSnapshots, $embedBudget);
        }

        return $html;
    }

    /**
     * @param  list<array<string, mixed>>  $visibleBlocks
     * @param  Collection<int, Snapshot>  $embedSnapshots  keyed by id
     */
    private static function build(
        array $visibleBlocks,
        int $totalBlocks,
        int $shownCount,
        Collection $embedSnapshots,
        int $embedBudget,
    ): string {
        $body = '';

        foreach ($visibleBlocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';

            if ($type === 'markdown') {
                $md = is_string($block['body'] ?? null) ? $block['body'] : '';
                $body .= '<section data-block-index="'.$index.'" data-block-type="markdown">'
                    .'<pre>'.e($md).'</pre>'
                    .'</section>';

                continue;
            }

            if ($type === 'embed') {
                $body .= self::renderEmbedCard($index, $block, $embedSnapshots, $embedBudget);
            }
        }

        $footer = $shownCount < $totalBlocks
            ? '<footer data-truncated="true">Showing '.$shownCount.' of '.$totalBlocks.' blocks</footer>'
            : '';

        return '<div data-nexus-preview="report" data-block-count="'.$totalBlocks.'">'.$body.$footer.'</div>';
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, Snapshot>  $embedSnapshots
     */
    private static function renderEmbedCard(
        int $index,
        array $block,
        Collection $embedSnapshots,
        int $embedBudget,
    ): string {
        $snapshotId = $block['snapshot_id'] ?? null;
        $snapshot = is_int($snapshotId) ? $embedSnapshots->get($snapshotId) : null;

        if ($snapshot === null || $snapshot->currentVersion === null) {
            return '<section data-block-index="'.$index.'" data-block-type="embed" data-restricted="true">'
                .'<header>Embed unavailable</header>'
                .'</section>';
        }

        $title = (string) ($snapshot->title ?? $snapshot->slug);
        $viewType = (string) $snapshot->currentVersion->view_type;
        $preview = (string) ($snapshot->currentVersion->preview_html ?? '');

        // Per-embed byte budget — strip cached preview HTML to fit.
        if (strlen($preview) > $embedBudget) {
            $preview = substr($preview, 0, max(0, $embedBudget)).'<!-- truncated -->';
        }

        return '<section data-block-index="'.$index.'" data-block-type="embed"'
            .' data-embedded-snapshot-id="'.(int) $snapshot->id.'"'
            .' data-embedded-view-type="'.e($viewType).'">'
            .'<header>'
            .'<span class="title">'.e($title).'</span>'
            .'<span class="badge" data-view-type="'.e($viewType).'">'.e($viewType).'</span>'
            .'</header>'
            .'<div class="embed-preview">'.$preview.'</div>'
            .'</section>';
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private static function estimateMarkdownBytes(array $blocks): int
    {
        $bytes = 0;

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) !== 'markdown') {
                continue;
            }

            $body = $block['body'] ?? '';

            if (is_string($body)) {
                // Approximate post-escape size (most markdown does not blow up).
                $bytes += strlen($body) + 64; // 64 bytes for the wrapping <section>/<pre> tags.
            }
        }

        return $bytes;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private static function countEmbeds(array $blocks): int
    {
        $count = 0;

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'embed') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return Collection<int, Snapshot>
     */
    private static function loadEmbeddedSnapshots(array $blocks): Collection
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'embed' && is_int($block['snapshot_id'] ?? null)) {
                $ids[] = (int) $block['snapshot_id'];
            }
        }

        if ($ids === []) {
            /** @var Collection<int, Snapshot> $empty */
            $empty = collect();

            return $empty;
        }

        return Snapshot::query()
            ->whereIn('id', $ids)
            ->with('currentVersion:id,view_type,preview_html')
            ->get(['id', 'slug', 'title', 'current_version_id'])
            ->keyBy('id');
    }
}

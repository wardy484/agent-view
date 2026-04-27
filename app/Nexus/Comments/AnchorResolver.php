<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Models\Comment;
use App\Models\SnapshotVersion;

/**
 * REQ-M6-004: resolve a comment's hybrid anchor (block_id + quote +
 * prefix/suffix + start/end hints) against a `SnapshotVersion`'s report
 * payload. Pure: no DB writes, no Eloquent saves. The caller decides what
 * to do with a stale result — typically the snapshot show endpoint flips
 * the comment row's `status` lazily, but read paths must never block on
 * resolution.
 */
final class AnchorResolver
{
    public static function resolve(Comment $c, SnapshotVersion $v): ResolvedAnchor
    {
        // Defensive: comments only target report blocks (REQ-M6-001).
        if ($v->view_type !== 'report') {
            return ResolvedAnchor::stale('non-report view');
        }

        $payload = $v->data_payload;
        $blocks = is_array($payload) && isset($payload['blocks']) && is_array($payload['blocks'])
            ? $payload['blocks']
            : [];

        $block = null;
        $blockIndex = null;
        foreach ($blocks as $index => $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if (($candidate['id'] ?? null) === $c->block_id) {
                $block = $candidate;
                $blockIndex = $index;
                break;
            }
        }

        if ($block === null) {
            return ResolvedAnchor::stale('block missing');
        }

        // M6 v1 scopes comments to markdown blocks only.
        if (($block['type'] ?? null) === 'embed') {
            return ResolvedAnchor::stale('block is embed');
        }

        $body = (string) ($block['body'] ?? '');
        $quote = (string) $c->anchor_quote;

        // 1. Try the hint window first.
        $startHint = $c->anchor_start_hint;
        $endHint = $c->anchor_end_hint;
        if (
            $startHint !== null
            && $endHint !== null
            && $startHint >= 0
            && $endHint <= strlen($body)
            && $endHint - $startHint === strlen($quote)
            && substr($body, $startHint, $endHint - $startHint) === $quote
        ) {
            return ResolvedAnchor::open($c->block_id, $startHint, $endHint);
        }

        // 2. Find ALL occurrences of the quote in the body.
        $occurrences = self::findAllOccurrences($body, $quote);

        if ($occurrences === []) {
            return self::resolveAcrossMarkdownBlocks($c, $blocks, (int) $blockIndex, $body, $quote);
        }

        if (count($occurrences) === 1) {
            $start = $occurrences[0];

            return ResolvedAnchor::open($c->block_id, $start, $start + strlen($quote));
        }

        // 3. Disambiguate via prefix/suffix.
        $prefix = (string) ($c->anchor_prefix ?? '');
        $suffix = (string) ($c->anchor_suffix ?? '');
        $valid = [];
        foreach ($occurrences as $start) {
            $actualPrefix = $prefix === ''
                ? ''
                : substr($body, max(0, $start - strlen($prefix)), min($start, strlen($prefix)));
            $actualSuffix = $suffix === ''
                ? ''
                : substr($body, $start + strlen($quote), strlen($suffix));

            $expectedPrefix = strlen($prefix) > $start
                ? substr($prefix, -$start)
                : $prefix;
            $remaining = strlen($body) - ($start + strlen($quote));
            $expectedSuffix = strlen($suffix) > $remaining
                ? substr($suffix, 0, $remaining)
                : $suffix;

            if ($actualPrefix === $expectedPrefix && $actualSuffix === $expectedSuffix) {
                $valid[] = $start;
            }
        }

        if (count($valid) === 1) {
            $start = $valid[0];

            return ResolvedAnchor::open($c->block_id, $start, $start + strlen($quote));
        }

        if ($valid === []) {
            return ResolvedAnchor::stale('quote ambiguous');
        }

        return ResolvedAnchor::stale('quote ambiguous after disambiguation');
    }

    /**
     * Resolve a read-only comment anchor whose selected quote begins in the
     * anchor block and continues through later markdown blocks.
     *
     * @param  list<mixed>  $blocks
     */
    private static function resolveAcrossMarkdownBlocks(
        Comment $comment,
        array $blocks,
        int $startBlockIndex,
        string $startBody,
        string $quote,
    ): ResolvedAnchor {
        $startHint = $comment->anchor_start_hint;

        if ($startHint === null || $startHint < 0 || $startHint > strlen($startBody)) {
            return ResolvedAnchor::stale('quote not found');
        }

        $remaining = $quote;
        $cursor = $startHint;

        for ($index = $startBlockIndex; $index < count($blocks); $index++) {
            $candidate = $blocks[$index] ?? null;

            if (! is_array($candidate) || ($candidate['type'] ?? null) === 'embed') {
                return ResolvedAnchor::stale('quote crosses non-markdown block');
            }

            $body = (string) ($candidate['body'] ?? '');
            $bodyRemainder = substr($body, $cursor);
            $matched = self::commonPrefixLength($remaining, $bodyRemainder);

            if ($matched === 0 && $remaining !== '') {
                return ResolvedAnchor::stale('quote not found');
            }

            $remaining = substr($remaining, $matched);

            if ($remaining === '') {
                return ResolvedAnchor::open($comment->block_id, $startHint, $startHint + strlen($quote));
            }

            $remaining = ltrim($remaining, "\r\n");
            $cursor = 0;
        }

        return ResolvedAnchor::stale('quote not found');
    }

    private static function commonPrefixLength(string $left, string $right): int
    {
        $max = min(strlen($left), strlen($right));

        for ($i = 0; $i < $max; $i++) {
            if ($left[$i] !== $right[$i]) {
                return $i;
            }
        }

        return $max;
    }

    /**
     * @return array<int, int>
     */
    private static function findAllOccurrences(string $haystack, string $needle): array
    {
        if ($needle === '') {
            return [];
        }

        $offsets = [];
        $offset = 0;
        while (($pos = strpos($haystack, $needle, $offset)) !== false) {
            $offsets[] = $pos;
            $offset = $pos + 1;
        }

        return $offsets;
    }
}

<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

/**
 * REQ-M6-004: outcome of resolving a comment's anchor against a snapshot
 * version's report payload. Pure value object — never persisted directly.
 *
 * `status === 'open'` carries the located block id and `[start, end)` byte
 * offsets within that block's `body`. `status === 'stale'` carries a short
 * machine-readable `reason` string explaining why resolution failed.
 */
final readonly class ResolvedAnchor
{
    /**
     * @param  'open'|'stale'  $status
     */
    private function __construct(
        public string $status,
        public ?string $blockId,
        public ?int $start,
        public ?int $end,
        public ?string $reason,
    ) {}

    public static function open(string $blockId, int $start, int $end): self
    {
        return new self(
            status: 'open',
            blockId: $blockId,
            start: $start,
            end: $end,
            reason: null,
        );
    }

    public static function stale(string $reason): self
    {
        return new self(
            status: 'stale',
            blockId: null,
            start: null,
            end: null,
            reason: $reason,
        );
    }
}

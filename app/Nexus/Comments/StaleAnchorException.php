<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use RuntimeException;

/**
 * REQ-M6-008: thrown by {@see SuggestionAcceptanceService::accept()} when the
 * comment's anchor cannot be resolved against the snapshot's current
 * revision. Carries the resolver's machine-readable reason verbatim so the
 * controller layer can surface it as JSON to the caller.
 */
final class StaleAnchorException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("comment anchor is stale: {$reason}");
    }
}

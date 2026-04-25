<?php

declare(strict_types=1);

namespace App\Nexus;

use RuntimeException;

/**
 * REQ-M7-004: thrown by {@see SnapshotVersioning::append()} when a caller
 * supplies a `metadata.orchestrator` blob whose JSON-serialised representation
 * exceeds 32 KB. The blob is otherwise free-form — only the size cap is
 * enforced server-side. Truncation is never silent.
 */
class OrchestratorMetadataTooLargeException extends RuntimeException
{
    //
}

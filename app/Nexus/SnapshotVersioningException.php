<?php

declare(strict_types=1);

namespace App\Nexus;

use RuntimeException;

/**
 * REQ-M1-009: thrown when something other than {@see SnapshotVersioning} tries
 * to insert or update a `snapshot_versions` row.
 */
class SnapshotVersioningException extends RuntimeException
{
    //
}

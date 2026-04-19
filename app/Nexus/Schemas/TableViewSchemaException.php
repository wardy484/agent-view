<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use RuntimeException;

/**
 * Thrown by {@see TableViewSchema::validate()} when a `data_payload` does not
 * conform to the v1 Table view schema (REQ-M1-008).
 */
class TableViewSchemaException extends RuntimeException
{
    //
}

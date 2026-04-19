<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use RuntimeException;

/**
 * Thrown by {@see KanbanViewSchema::validate()} when a `data_payload` does not
 * conform to the v1 Kanban view schema (REQ-M2-001).
 */
class KanbanViewSchemaException extends RuntimeException
{
    //
}

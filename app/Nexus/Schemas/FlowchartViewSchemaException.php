<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use RuntimeException;

/**
 * Thrown by {@see FlowchartViewSchema::validate()} when a `data_payload` does
 * not conform to the v1 Flowchart view schema (REQ-M2-004).
 */
class FlowchartViewSchemaException extends RuntimeException
{
    //
}

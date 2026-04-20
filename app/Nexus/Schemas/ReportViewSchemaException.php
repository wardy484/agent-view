<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use RuntimeException;

/**
 * Thrown by {@see ReportViewSchema::validate()} when a `data_payload` does
 * not conform to the v1 Report view schema (REQ-M5-001).
 */
class ReportViewSchemaException extends RuntimeException
{
    //
}

<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use RuntimeException;

/**
 * Thrown by {@see SlideDeckViewSchema::validate()} when a `data_payload` does
 * not conform to the v1 Slide Deck view schema (REQ-M3-001).
 */
class SlideDeckViewSchemaException extends RuntimeException
{
    //
}

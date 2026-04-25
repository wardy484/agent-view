<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * REQ-M6-003: lifecycle of a comment thread.
 *
 * - {@see self::Open}: default; awaiting attention.
 * - {@see self::Resolved}: addressed in a later revision.
 * - {@see self::Stale}: anchor no longer resolves in the current revision.
 * - {@see self::Wontfix}: explicitly dismissed.
 */
enum CommentStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Stale = 'stale';
    case Wontfix = 'wontfix';
}

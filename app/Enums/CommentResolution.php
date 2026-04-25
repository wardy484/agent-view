<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * REQ-M6-003: identifies who resolved a comment thread — a human owner or
 * the agent acting via {@see resolve_comments}.
 */
enum CommentResolution: string
{
    case User = 'user';
    case Agent = 'agent';
}

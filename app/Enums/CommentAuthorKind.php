<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * REQ-M6-003: distinguishes human-authored comments from agent replies
 * posted via {@see reply_to_comment}.
 */
enum CommentAuthorKind: string
{
    case User = 'user';
    case Agent = 'agent';
}

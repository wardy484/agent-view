<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * REQ-M6-003: a root comment is either a plain comment or a suggestion that
 * proposes a verbatim replacement for {@see anchor_quote}. Replies always
 * carry kind = comment.
 */
enum CommentKind: string
{
    case Comment = 'comment';
    case Suggestion = 'suggestion';
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * REQ-M6-007: the fixed set of emojis users can react with on a comment.
 * Mirrors the CHECK constraint on `comment_reactions.emoji` — keep the two
 * lists in sync.
 */
enum CommentReactionEmoji: string
{
    case ThumbsUp = '👍';
    case ThumbsDown = '👎';
    case Heart = '❤️';
    case Tada = '🎉';
    case Thinking = '🤔';
    case Eyes = '👀';
}

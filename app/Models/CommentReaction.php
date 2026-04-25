<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentReactionEmoji;
use App\Nexus\Comments\CommentReactionService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-M6-007: a single user's reaction on a comment with one emoji.
 *
 * Has no `updated_at`: a reaction is either present or absent, so there is
 * nothing to mutate after insert. The unique index on
 * `(comment_id, user_id, emoji)` ties this row to the toggle invariant
 * enforced by {@see CommentReactionService::toggle()}.
 */
#[Fillable([
    'comment_id',
    'user_id',
    'emoji',
])]
class CommentReaction extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emoji' => CommentReactionEmoji::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

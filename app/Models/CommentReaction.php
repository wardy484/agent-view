<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentReactionEmoji;
use App\Nexus\Comments\CommentReactionService;
use App\Nexus\Comments\CommentsRevisionTracker;
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
     * REQ-M6-016: reaction toggles are a sidebar-visible mutation, so they
     * bump the snapshot's `comments_revision` via the parent comment.
     */
    protected static function booted(): void
    {
        static::created(function (CommentReaction $reaction): void {
            CommentsRevisionTracker::bump($reaction->comment?->snapshot_id);
        });

        static::deleted(function (CommentReaction $reaction): void {
            CommentsRevisionTracker::bump($reaction->comment?->snapshot_id);
        });
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

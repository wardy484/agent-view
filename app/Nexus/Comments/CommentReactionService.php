<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Enums\CommentReactionEmoji;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * REQ-M6-007: toggle a user's reaction on a comment.
 *
 * The unique `(comment_id, user_id, emoji)` index makes "second click
 * removes" a check-then-insert/delete; the call is wrapped in a DB
 * transaction so concurrent toggles can't race past each other.
 */
final class CommentReactionService
{
    /**
     * Add the reaction if absent, remove it if already present.
     *
     * @return bool `true` when the reaction was added, `false` when it was
     *              removed.
     */
    public function toggle(Comment $comment, User $user, CommentReactionEmoji $emoji): bool
    {
        return DB::transaction(function () use ($comment, $user, $emoji): bool {
            $existing = CommentReaction::query()
                ->where('comment_id', $comment->getKey())
                ->where('user_id', $user->getKey())
                ->where('emoji', $emoji->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return false;
            }

            CommentReaction::query()->create([
                'comment_id' => $comment->getKey(),
                'user_id' => $user->getKey(),
                'emoji' => $emoji->value,
            ]);

            return true;
        });
    }
}

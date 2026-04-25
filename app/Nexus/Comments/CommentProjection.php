<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Mcp\Tools\GetSnapshotComments;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use Illuminate\Support\Collection;

/**
 * REQ-M6-014: shared projection for comment payloads. Both
 * {@see GetSnapshotComments} and the snapshot show endpoint
 * (Inertia `comments` prop) consume this so the agent-facing JSON and the
 * UI-facing JSON keep the same shape — anchor flags, threads, reactions,
 * and the suggestion-only `proposed_text` field.
 */
class CommentProjection
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forSnapshot(Snapshot $snapshot, ?SnapshotVersion $currentVersion): array
    {
        $roots = Comment::query()
            ->roots()
            ->where('snapshot_id', $snapshot->id)
            ->with(['author', 'createdOnVersion', 'addressedOnVersion', 'replies.author', 'reactions'])
            ->orderBy('id')
            ->get();

        return $roots
            ->map(fn (Comment $comment): array => self::project($comment, $currentVersion))
            ->all();
    }

    /**
     * @param  Collection<int, Comment>  $roots
     * @return list<array<string, mixed>>
     */
    public static function projectMany(Collection $roots, ?SnapshotVersion $currentVersion): array
    {
        return $roots
            ->map(fn (Comment $comment): array => self::project($comment, $currentVersion))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function project(Comment $comment, ?SnapshotVersion $currentVersion): array
    {
        $resolved = $currentVersion !== null
            ? AnchorResolver::resolve($comment, $currentVersion)
            : null;

        $payload = [
            'id' => (int) $comment->id,
            'block_id' => (string) $comment->block_id,
            'status' => $comment->status->value,
            'anchor' => [
                'quote' => (string) $comment->anchor_quote,
                'prefix' => (string) ($comment->anchor_prefix ?? ''),
                'suffix' => (string) ($comment->anchor_suffix ?? ''),
                'resolved_in_current_version' => $resolved?->status === 'open',
            ],
            'body' => (string) $comment->body,
            'kind' => $comment->kind->value,
            'author' => [
                'display_name' => $comment->author?->name ?? 'Unknown',
                'kind' => $comment->author_kind->value,
            ],
            'created_at' => $comment->created_at?->toIso8601String(),
            'created_on_revision' => $comment->createdOnVersion?->revision,
            'addressed_on_revision' => $comment->addressedOnVersion?->revision,
            'thread' => $comment->replies->map(fn (Comment $reply): array => [
                'id' => (int) $reply->id,
                'body' => (string) $reply->body,
                'author' => [
                    'display_name' => $reply->author?->name ?? 'Unknown',
                    'kind' => $reply->author_kind->value,
                ],
                'created_at' => $reply->created_at?->toIso8601String(),
            ])->all(),
            'reactions_summary' => $comment->reactionsSummary(),
        ];

        if ($comment->kind->value === 'suggestion') {
            $payload['proposed_text'] = (string) ($comment->proposed_text ?? '');
        }

        return $payload;
    }
}

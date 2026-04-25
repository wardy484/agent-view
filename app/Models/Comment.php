<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Nexus\Comments\CommentsRevisionTracker;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * REQ-M6-003: inline review comment on a report markdown block.
 *
 * Root comments anchor to a block via the `anchor_*` quote columns; replies
 * leave those null and chain to the root via {@see self::parent}. Replies
 * are restricted to depth = 1 by a database trigger; both shapes (reply,
 * suggestion) are also enforced via CHECK constraints on the table.
 */
#[Fillable([
    'snapshot_id',
    'block_id',
    'parent_comment_id',
    'kind',
    'body',
    'proposed_text',
    'anchor_quote',
    'anchor_prefix',
    'anchor_suffix',
    'anchor_start_hint',
    'anchor_end_hint',
    'status',
    'resolution',
    'created_on_version_id',
    'addressed_on_version_id',
    'author_user_id',
    'author_kind',
])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CommentKind::class,
            'status' => CommentStatus::class,
            'resolution' => CommentResolution::class,
            'author_kind' => CommentAuthorKind::class,
            'anchor_start_hint' => 'integer',
            'anchor_end_hint' => 'integer',
        ];
    }

    /**
     * REQ-M6-012: `created_on_version_id` is immutable once a comment row
     * exists. The column anchors a thread to the revision it was filed
     * against; mutating it after creation would silently shift comment
     * provenance and break the stale/auto-revive contract.
     */
    protected static function booted(): void
    {
        static::updating(function (Comment $comment): void {
            if ($comment->isDirty('created_on_version_id')) {
                throw new \LogicException('Comment::created_on_version_id is immutable');
            }
        });

        // REQ-M6-016: every comment-graph mutation moves the snapshot's
        // `comments_revision` counter forward so the sidebar polling loop
        // sees a delta. Updates only bump on status / resolution flips and
        // body edits — silent metadata changes (e.g. timestamp touches) do
        // not need to wake every connected tab.
        static::created(function (Comment $comment): void {
            CommentsRevisionTracker::bump($comment->snapshot_id);
        });

        static::updated(function (Comment $comment): void {
            $watched = ['status', 'resolution', 'body', 'addressed_on_version_id', 'deleted_at'];

            foreach ($watched as $column) {
                if ($comment->wasChanged($column)) {
                    CommentsRevisionTracker::bump($comment->snapshot_id);

                    return;
                }
            }
        });

        static::deleted(function (Comment $comment): void {
            CommentsRevisionTracker::bump($comment->snapshot_id);
        });

        static::restored(function (Comment $comment): void {
            CommentsRevisionTracker::bump($comment->snapshot_id);
        });

        static::forceDeleted(function (Comment $comment): void {
            CommentsRevisionTracker::bump($comment->snapshot_id);
        });
    }

    /**
     * @return BelongsTo<Snapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    /**
     * REQ-M6-006: replies are returned inline with their parent ordered by
     * `created_at ASC` within a thread.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id')
            ->orderBy('created_at');
    }

    /**
     * REQ-M6-006: only root comments (those with `parent_comment_id IS NULL`)
     * carry anchors; replies are excluded from this scope so callers can fetch
     * the top of each thread without filtering manually.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_comment_id');
    }

    /**
     * REQ-M6-006: convenience accessor — true when this comment is a reply
     * to a root comment.
     */
    public function isReply(): bool
    {
        return $this->parent_comment_id !== null;
    }

    /**
     * REQ-M6-006: convenience accessor — true when this comment is a root
     * comment (i.e. anchored to a block, not nested under another comment).
     */
    public function isRoot(): bool
    {
        return $this->parent_comment_id === null;
    }

    /**
     * REQ-M6-006: returns this root comment followed by its replies, ordered
     * by `created_at ASC`. Calling this on a reply throws — only roots own a
     * thread.
     *
     * @return Collection<int, Comment>
     */
    public function thread(): Collection
    {
        if ($this->isReply()) {
            throw new \LogicException('thread() may only be called on a root comment.');
        }

        return collect([$this])->concat($this->replies()->get())->values();
    }

    /**
     * REQ-M6-007: emoji reactions placed on this comment by users. Both root
     * comments and replies may carry reactions.
     *
     * @return HasMany<CommentReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }

    /**
     * REQ-M6-007: returns a `[emoji => count]` map for this comment's
     * reactions. Used by the agent-facing comment listing tool to surface
     * a compact reactions summary per thread node.
     *
     * @return array<string, int>
     */
    public function reactionsSummary(): array
    {
        return $this->reactions()
            ->selectRaw('emoji, COUNT(*) AS reaction_count')
            ->groupBy('emoji')
            ->pluck('reaction_count', 'emoji')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<SnapshotVersion, $this>
     */
    public function createdOnVersion(): BelongsTo
    {
        return $this->belongsTo(SnapshotVersion::class, 'created_on_version_id');
    }

    /**
     * @return BelongsTo<SnapshotVersion, $this>
     */
    public function addressedOnVersion(): BelongsTo
    {
        return $this->belongsTo(SnapshotVersion::class, 'addressed_on_version_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id');
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

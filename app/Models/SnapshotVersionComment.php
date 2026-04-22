<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SnapshotVersionCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AGENT-RENDER-6: a comment pinned to a specific snapshot version.
 *
 * Comments soft-delete to preserve thread structure when a parent is removed.
 * {@see self::resolve()} / {@see self::unresolve()} toggle `resolved_at` so
 * the UI can collapse resolved threads without losing the discussion.
 */
#[Fillable(['snapshot_version_id', 'user_id', 'parent_id', 'body', 'resolved_at'])]
class SnapshotVersionComment extends Model
{
    /** @use HasFactory<SnapshotVersionCommentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function resolve(): void
    {
        if ($this->resolved_at === null) {
            $this->forceFill(['resolved_at' => now()])->save();
        }
    }

    public function unresolve(): void
    {
        if ($this->resolved_at !== null) {
            $this->forceFill(['resolved_at' => null])->save();
        }
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * @return BelongsTo<SnapshotVersion, $this>
     */
    public function snapshotVersion(): BelongsTo
    {
        return $this->belongsTo(SnapshotVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SnapshotVersionComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<SnapshotVersionComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * @return HasMany<SnapshotCommentAnchor, $this>
     */
    public function anchors(): HasMany
    {
        return $this->hasMany(SnapshotCommentAnchor::class, 'comment_id');
    }
}

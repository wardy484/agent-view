<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SnapshotCommentAnchorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AGENT-RENDER-6: inline anchor pinning a comment to a region of the view.
 *
 * `anchor_data` is an opaque JSONB payload whose shape is owned by the view
 * renderer that produced it (e.g. a table renderer stores `row`/`column`, a
 * report renderer stores DOM `element_id` + `offset`). Keep parsing out of
 * this model — consumers validate when they interpret.
 */
#[Fillable(['comment_id', 'anchor_type', 'anchor_data'])]
class SnapshotCommentAnchor extends Model
{
    /** @use HasFactory<SnapshotCommentAnchorFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchor_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SnapshotVersionComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(SnapshotVersionComment::class, 'comment_id');
    }
}

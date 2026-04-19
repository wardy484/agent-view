<?php

declare(strict_types=1);

namespace App\Models;

use App\Nexus\SnapshotVersioning;
use App\Nexus\SnapshotVersioningException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['snapshot_id', 'revision', 'view_type', 'data_payload', 'metadata', 'preview_html'])]
class SnapshotVersion extends Model
{
    /**
     * REQ-M1-009: every persistent write to `snapshot_versions` must flow
     * through {@see SnapshotVersioning::append()}. The saving hook below
     * blocks anything else.
     */
    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! SnapshotVersioning::isWriting()) {
                throw new SnapshotVersioningException(
                    'Direct writes to snapshot_versions are forbidden — call SnapshotVersioning::append() instead.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_payload' => 'array',
            'metadata' => 'array',
            'revision' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Snapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }
}

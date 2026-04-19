<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workbench_id', 'snapshot_id', 'payload', 'consumed_at'])]
class FollowUpContext extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workbench, $this>
     */
    public function workbench(): BelongsTo
    {
        return $this->belongsTo(Workbench::class);
    }

    /**
     * @return BelongsTo<Snapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }
}

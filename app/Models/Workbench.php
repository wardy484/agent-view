<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkbenchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['slug', 'name', 'owner_user_id', 'pinned_at', 'archived_at', 'last_activity_at'])]
class Workbench extends Model
{
    /** @use HasFactory<WorkbenchFactory> */
    use HasFactory;

    /**
     * REQ-M6-000: soft-deletion powers the dashboard Trash tab and the
     * 30-day recovery window implemented by {@see REQ-M6-005}.
     */
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // REQ-M6-000: new organisational timestamps cast to Carbon so
            // callers can compare with now(), compute "days archived", etc.
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Snapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    /**
     * REQ-M4-000: owning user. Nullable — local stdio / system-created
     * workbenches have no owner and are never shareable (REQ-M4-005).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}

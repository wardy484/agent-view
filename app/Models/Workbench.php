<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkbenchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'owner_user_id'])]
class Workbench extends Model
{
    /** @use HasFactory<WorkbenchFactory> */
    use HasFactory;

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

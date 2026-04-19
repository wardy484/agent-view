<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workbench_id', 'slug', 'title', 'current_version_id'])]
class Snapshot extends Model
{
    /** @use HasFactory<SnapshotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Workbench, $this>
     */
    public function workbench(): BelongsTo
    {
        return $this->belongsTo(Workbench::class);
    }

    /**
     * @return HasMany<SnapshotVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SnapshotVersion::class)->orderBy('revision');
    }

    /**
     * @return BelongsTo<SnapshotVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SnapshotVersion::class, 'current_version_id');
    }
}

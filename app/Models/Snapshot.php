<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SnapshotVisibility;
use Database\Factories\SnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workbench_id', 'slug', 'title', 'current_version_id', 'visibility'])]
class Snapshot extends Model
{
    /** @use HasFactory<SnapshotFactory> */
    use HasFactory;

    /**
     * REQ-M4-001: default visibility is 'private' so new models mirror the
     * DB-level default without needing a fresh() round-trip.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // REQ-M4-001: visibility is an enum cast so reads/writes always
            // round-trip through {@see SnapshotVisibility}.
            'visibility' => SnapshotVisibility::class,
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

    /**
     * REQ-M4-003: active (non-revoked) shares.  For the full audit trail
     * including revoked rows, use {@see self::allShares()}.
     *
     * @return HasMany<SnapshotShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(SnapshotShare::class)->whereNull('revoked_at');
    }

    /**
     * @return HasMany<SnapshotShare, $this>
     */
    public function allShares(): HasMany
    {
        return $this->hasMany(SnapshotShare::class);
    }
}

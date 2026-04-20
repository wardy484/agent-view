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

#[Fillable(['workbench_id', 'slug', 'title', 'current_version_id', 'visibility', 'share_token'])]
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

    /**
     * REQ-M4-002: atomically transition sharing state.
     *
     * Private → Link: mint a fresh 32-byte URL-safe `share_token`.
     * Link → Link: no rotation (idempotent).
     * *     → Private: clear token.
     * *     → Shared: clear token (link mode is off).
     */
    public function setVisibility(SnapshotVisibility $next): void
    {
        $current = $this->visibility;

        $updates = ['visibility' => $next];

        if ($next === SnapshotVisibility::Link) {
            if ($current !== SnapshotVisibility::Link || $this->share_token === null) {
                $updates['share_token'] = self::mintShareToken();
            }
        } else {
            // Any non-Link destination retires the link token.
            $updates['share_token'] = null;
        }

        $this->forceFill($updates)->save();
    }

    /**
     * REQ-M4-002: 32 random bytes, URL-safe base64 encoded (43 characters,
     * ~256 bits of entropy). Sufficient that brute-forcing a token is
     * computationally infeasible even against millions of snapshots.
     */
    public static function mintShareToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

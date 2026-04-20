<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SnapshotShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-M4-003: allowlist row for `visibility=shared` snapshots.
 *
 * Shares soft-revoke via {@see self::revoke()} — `revoked_at` is set and the
 * partial unique index (snapshot_id, email) WHERE revoked_at IS NULL) releases
 * the slot so the same email can be re-invited later.
 *
 * Emails are stored lowercased so policy lookups (REQ-M4-005) can compare
 * directly against `$user->email` without additional normalisation.
 */
#[Fillable(['snapshot_id', 'email', 'user_id', 'granted_by_user_id', 'revoked_at', 'created_at'])]
class SnapshotShare extends Model
{
    /** @use HasFactory<SnapshotShareFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * REQ-M4-003: email is always stored lowercased.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower(trim($value)),
        );
    }

    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<Snapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}

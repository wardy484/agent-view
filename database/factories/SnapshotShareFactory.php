<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SnapshotShare>
 */
class SnapshotShareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snapshot_id' => Snapshot::factory(),
            'email' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'granted_by_user_id' => User::factory(),
            'created_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): self
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn () => [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
    }
}

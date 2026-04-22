<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SnapshotVersionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SnapshotVersionComment>
 */
class SnapshotVersionCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // snapshot_version_id has no factory (versions flow through
            // SnapshotVersioning::append); tests must set this explicitly.
            'snapshot_version_id' => null,
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->sentence(),
            'resolved_at' => null,
        ];
    }

    public function resolved(): self
    {
        return $this->state(fn () => ['resolved_at' => now()]);
    }

    public function replyTo(SnapshotVersionComment $parent): self
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'snapshot_version_id' => $parent->snapshot_version_id,
        ]);
    }

    public function system(): self
    {
        return $this->state(fn () => ['user_id' => null]);
    }
}

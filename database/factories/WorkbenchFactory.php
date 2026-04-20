<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Workbench;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workbench>
 */
class WorkbenchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            // REQ-M4-000: factories default to an owned workbench so the bulk
            // of the suite exercises the common (ownable, shareable) shape.
            // Tests that need the system-owned case pass ['owner_user_id' => null].
            'owner_user_id' => User::factory(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

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
        ];
    }
}

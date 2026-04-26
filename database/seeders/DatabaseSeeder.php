<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'wardy484@gmail.com'],
            [
                'name' => 'Wardy',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $this->call(DemoSnapshotSeeder::class);

        // REQ-M11-001: the browser test fixtures are scoped to the testing
        // environment so they only land in CI/local Pest runs and never
        // contaminate dev or production databases. Browser tests invoke
        // the seeder directly via $this->seed(BrowserTestSeeder::class).
        if (app()->environment('testing')) {
            $this->call(BrowserTestSeeder::class);
        }
    }
}

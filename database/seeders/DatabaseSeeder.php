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
    }
}

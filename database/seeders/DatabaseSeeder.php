<?php

// This seeder initializes baseline data for the application.
namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\TierSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// This class seeds a default user and tiers for local development.
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // This ensures a test user exists for local access.
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // This seeds the default tier configuration.
        $this->call(TierSeeder::class);
    }
}

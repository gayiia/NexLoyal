<?php

namespace Database\Seeders;

use App\Models\Tier;
use Illuminate\Database\Seeder;

class TierSeeder extends Seeder
{
    public function run(): void
    {
        Tier::query()->delete();

        Tier::insert([
            [
                'title' => 'Bronze',
                'color' => '#f59e0b',
                'min_points' => 0,
                'max_points' => 999,
                'single_point_value' => 1.00,
                'description' => 'Starter tier.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Silver',
                'color' => '#94a3b8',
                'min_points' => 1000,
                'max_points' => 4999,
                'single_point_value' => 1.20,
                'description' => 'Growing loyalty.',
                'status' => 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Gold',
                'color' => '#facc15',
                'min_points' => 5000,
                'max_points' => 9999,
                'single_point_value' => 1.50,
                'description' => 'Top tier rewards.',
                'status' => 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

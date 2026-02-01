<?php

// This seeder inserts default loyalty tiers for the app.
namespace Database\Seeders;

use App\Models\Tier;
use Illuminate\Database\Seeder;

// This class seeds a baseline set of tiers with point ranges.
class TierSeeder extends Seeder
{
    // This wipes existing tiers and inserts the default set.
    public function run(): void
    {
        // This ensures a clean slate before inserting defaults.
        Tier::query()->delete();

        // These rows define the initial tier thresholds and labels.
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

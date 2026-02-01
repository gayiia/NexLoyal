<?php

// This migration adds optional profile tracking fields to customers.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns support birthday rewards and profile completion tracking.
        Schema::table('customers', function (Blueprint $table): void {
            // These checks avoid errors if the columns already exist.
            if (!Schema::hasColumn('customers', 'birthday')) {
                $table->date('birthday')->nullable()->after('email');
            }
            if (!Schema::hasColumn('customers', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable()->after('birthday');
            }
            if (!Schema::hasColumn('customers', 'birthday_rewarded_at')) {
                $table->date('birthday_rewarded_at')->nullable()->after('profile_completed_at');
            }
        });
    }

    public function down(): void
    {
        // This removes profile columns that were added in up().
        Schema::table('customers', function (Blueprint $table): void {
            $columns = [];
            foreach (['birthday', 'profile_completed_at', 'birthday_rewarded_at'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};

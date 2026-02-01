<?php

// This migration adds customer feature flags used for AI filtering.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns track tenure and exclusion status for AI features.
        Schema::table('customer_features', function (Blueprint $table): void {
            if (!Schema::hasColumn('customer_features', 'tenure_days')) {
                $table->unsignedInteger('tenure_days')->nullable()->after('days_since_last_order');
            }
            if (!Schema::hasColumn('customer_features', 'is_new_customer')) {
                $table->boolean('is_new_customer')->default(false)->after('tenure_days');
            }
            if (!Schema::hasColumn('customer_features', 'is_excluded')) {
                $table->boolean('is_excluded')->default(false)->after('is_new_customer');
            }
            if (!Schema::hasColumn('customer_features', 'excluded_reason')) {
                $table->string('excluded_reason')->nullable()->after('is_excluded');
            }
        });
    }

    public function down(): void
    {
        // This removes the AI flag columns if they exist.
        $columns = ['tenure_days', 'is_new_customer', 'is_excluded', 'excluded_reason'];
        $toDrop = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('customer_features', $column)) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop) {
            Schema::table('customer_features', function (Blueprint $table) use ($toDrop): void {
                $table->dropColumn($toDrop);
            });
        }
    }
};

<?php

// This migration adds additional history fields to points transactions.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These checks avoid errors when columns already exist.
        if (!Schema::hasColumn('points_transactions', 'store_id')) {
            Schema::table('points_transactions', function (Blueprint $table): void {
                $table->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }
        if (!Schema::hasColumn('points_transactions', 'source_type')) {
            Schema::table('points_transactions', function (Blueprint $table): void {
                $table->string('source_type')->nullable()->after('source');
            });
        }
        if (!Schema::hasColumn('points_transactions', 'title')) {
            Schema::table('points_transactions', function (Blueprint $table): void {
                $table->string('title')->nullable()->after('reason');
            });
        }
        if (!Schema::hasColumn('points_transactions', 'reference_type')) {
            Schema::table('points_transactions', function (Blueprint $table): void {
                $table->string('reference_type')->nullable()->after('title');
            });
        }
        if (!Schema::hasColumn('points_transactions', 'reference_id')) {
            Schema::table('points_transactions', function (Blueprint $table): void {
                $table->string('reference_id')->nullable()->after('reference_type');
            });
        }
    }

    public function down(): void
    {
        // This drops the history columns if they exist.
        $columns = [
            'store_id',
            'source_type',
            'title',
            'reference_type',
            'reference_id',
        ];

        $toDrop = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('points_transactions', $column)) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop) {
            Schema::table('points_transactions', function (Blueprint $table) use ($toDrop): void {
                $table->dropColumn($toDrop);
            });
        }
    }
};

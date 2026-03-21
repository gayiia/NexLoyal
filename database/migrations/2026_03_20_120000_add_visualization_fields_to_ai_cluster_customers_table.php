<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_cluster_customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_cluster_customers', 'days_since_last_order_snapshot')) {
                $table->unsignedInteger('days_since_last_order_snapshot')
                    ->default(0)
                    ->after('redeemed_coupons_snapshot');
            }

            if (!Schema::hasColumn('ai_cluster_customers', 'projection_x')) {
                $table->decimal('projection_x', 14, 6)
                    ->nullable()
                    ->after('days_since_last_order_snapshot');
            }

            if (!Schema::hasColumn('ai_cluster_customers', 'projection_y')) {
                $table->decimal('projection_y', 14, 6)
                    ->nullable()
                    ->after('projection_x');
            }

            if (!Schema::hasColumn('ai_cluster_customers', 'projection_method')) {
                $table->string('projection_method', 32)
                    ->nullable()
                    ->after('projection_y');
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'days_since_last_order_snapshot',
            'projection_x',
            'projection_y',
            'projection_method',
        ];

        $toDrop = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('ai_cluster_customers', $column)) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop !== []) {
            Schema::table('ai_cluster_customers', function (Blueprint $table) use ($toDrop): void {
                $table->dropColumn($toDrop);
            });
        }
    }
};

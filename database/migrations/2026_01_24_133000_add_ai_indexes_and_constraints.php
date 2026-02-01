<?php

// This migration adds indexes to speed up AI-related queries.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These indexes optimize feature and transaction lookups for AI.
        if (Schema::hasTable('customer_features')) {
            if (!$this->hasIndex('customer_features', 'customer_features_customer_id_idx')) {
                Schema::table('customer_features', function (Blueprint $table): void {
                    $table->index('customer_id', 'customer_features_customer_id_idx');
                });
            }
        }

        // These indexes improve query performance on points transactions.
        if (Schema::hasTable('points_transactions')) {
            if (!$this->hasIndex('points_transactions', 'points_transactions_customer_id_idx')) {
                Schema::table('points_transactions', function (Blueprint $table): void {
                    $table->index('customer_id', 'points_transactions_customer_id_idx');
                });
            }
            if (!$this->hasIndex('points_transactions', 'points_transactions_created_at_idx')) {
                Schema::table('points_transactions', function (Blueprint $table): void {
                    $table->index('created_at', 'points_transactions_created_at_idx');
                });
            }
            if (Schema::hasColumn('points_transactions', 'source_type')
                && !$this->hasIndex('points_transactions', 'points_transactions_source_type_idx')) {
                Schema::table('points_transactions', function (Blueprint $table): void {
                    $table->index('source_type', 'points_transactions_source_type_idx');
                });
            }
        }

        // These indexes speed up cluster/customer joins.
        if (Schema::hasTable('ai_cluster_customers')) {
            if (!$this->hasIndex('ai_cluster_customers', 'ai_cluster_customers_customer_id_idx')) {
                Schema::table('ai_cluster_customers', function (Blueprint $table): void {
                    $table->index('customer_id', 'ai_cluster_customers_customer_id_idx');
                });
            }
            if (!$this->hasIndex('ai_cluster_customers', 'ai_cluster_customers_cluster_id_idx')) {
                Schema::table('ai_cluster_customers', function (Blueprint $table): void {
                    $table->index('ai_cluster_id', 'ai_cluster_customers_cluster_id_idx');
                });
            }
        }
    }

    public function down(): void
    {
        // This removes indexes added in up() when present.
        if (Schema::hasTable('customer_features') && $this->hasIndex('customer_features', 'customer_features_customer_id_idx')) {
            Schema::table('customer_features', function (Blueprint $table): void {
                $table->dropIndex('customer_features_customer_id_idx');
            });
        }

        if (Schema::hasTable('points_transactions')) {
            if ($this->hasIndex('points_transactions', 'points_transactions_customer_id_idx')) {
                Schema::table('points_transactions', function (Blueprint $table): void {
                    $table->dropIndex('points_transactions_customer_id_idx');
                });
            }
            if ($this->hasIndex('points_transactions', 'points_transactions_created_at_idx')) {
                Schema::table('points_transactions', function (Blueprint $table): void {
                    $table->dropIndex('points_transactions_created_at_idx');
                });
            }
            if ($this->hasIndex('points_transactions', 'points_transactions_source_type_idx')) {
                Schema::table('points_transactions', function (Blueprint $table): void {
                    $table->dropIndex('points_transactions_source_type_idx');
                });
            }
        }

        if (Schema::hasTable('ai_cluster_customers')) {
            if ($this->hasIndex('ai_cluster_customers', 'ai_cluster_customers_customer_id_idx')) {
                Schema::table('ai_cluster_customers', function (Blueprint $table): void {
                    $table->dropIndex('ai_cluster_customers_customer_id_idx');
                });
            }
            if ($this->hasIndex('ai_cluster_customers', 'ai_cluster_customers_cluster_id_idx')) {
                Schema::table('ai_cluster_customers', function (Blueprint $table): void {
                    $table->dropIndex('ai_cluster_customers_cluster_id_idx');
                });
            }
        }
    }

    // This checks for an index in the database to avoid duplicate creation.
    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $index]
        );

        return !empty($result);
    }
};

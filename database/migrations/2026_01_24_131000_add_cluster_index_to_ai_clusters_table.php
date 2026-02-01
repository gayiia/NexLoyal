<?php

// This migration adds a numeric cluster index for stable ordering.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This adds the cluster_index column when missing.
        Schema::table('ai_clusters', function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_clusters', 'cluster_index')) {
                $table->unsignedInteger('cluster_index')->nullable()->after('label');
                $table->index(['ai_cluster_run_id', 'cluster_index'], 'ai_clusters_run_index');
            }
        });
    }

    public function down(): void
    {
        // This removes the cluster_index column and index if present.
        if (Schema::hasColumn('ai_clusters', 'cluster_index')) {
            Schema::table('ai_clusters', function (Blueprint $table): void {
                $table->dropIndex('ai_clusters_run_index');
                $table->dropColumn('cluster_index');
            });
        }
    }
};

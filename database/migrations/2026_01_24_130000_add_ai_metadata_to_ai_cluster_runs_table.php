<?php

// This migration adds AI metadata fields to ai_cluster_runs.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns store model selection metrics and scaler data.
        Schema::table('ai_cluster_runs', function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_cluster_runs', 'selected_k')) {
                $table->unsignedInteger('selected_k')->nullable()->after('silhouette_score');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'final_inertia')) {
                $table->decimal('final_inertia', 12, 4)->nullable()->after('selected_k');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'silhouette_scores')) {
                $table->json('silhouette_scores')->nullable()->after('final_inertia');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'inertia_scores')) {
                $table->json('inertia_scores')->nullable()->after('silhouette_scores');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'data_stats')) {
                $table->json('data_stats')->nullable()->after('inertia_scores');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'timing')) {
                $table->json('timing')->nullable()->after('data_stats');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'scaler_mean')) {
                $table->json('scaler_mean')->nullable()->after('timing');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'scaler_scale')) {
                $table->json('scaler_scale')->nullable()->after('scaler_mean');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'feature_names')) {
                $table->json('feature_names')->nullable()->after('scaler_scale');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'outlier_caps')) {
                $table->json('outlier_caps')->nullable()->after('feature_names');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'log_transforms')) {
                $table->json('log_transforms')->nullable()->after('outlier_caps');
            }
            if (!Schema::hasColumn('ai_cluster_runs', 'model_metadata')) {
                $table->json('model_metadata')->nullable()->after('log_transforms');
            }
        });
    }

    public function down(): void
    {
        // This removes the metadata columns added in up().
        $columns = [
            'selected_k',
            'final_inertia',
            'silhouette_scores',
            'inertia_scores',
            'data_stats',
            'timing',
            'scaler_mean',
            'scaler_scale',
            'feature_names',
            'outlier_caps',
            'log_transforms',
            'model_metadata',
        ];

        $toDrop = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('ai_cluster_runs', $column)) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop) {
            Schema::table('ai_cluster_runs', function (Blueprint $table) use ($toDrop): void {
                $table->dropColumn($toDrop);
            });
        }
    }
};

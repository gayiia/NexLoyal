<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cluster_run_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('customer_count')->default(0);
            $table->decimal('avg_total_spent', 12, 2)->nullable();
            $table->decimal('avg_orders_count', 12, 2)->nullable();
            $table->decimal('avg_loyalty_points', 12, 2)->nullable();
            $table->decimal('avg_points_spent', 12, 2)->nullable();
            $table->json('centroid')->nullable();
            $table->timestamps();

            $table->index(['ai_cluster_run_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_clusters');
    }
};

<?php

// This migration creates the ai_cluster_customers table for assignments.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table stores per-customer cluster assignments and snapshots.
        Schema::create('ai_cluster_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cluster_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_cluster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_spent_snapshot', 12, 2)->default(0);
            $table->unsignedInteger('orders_count_snapshot')->default(0);
            $table->unsignedInteger('loyalty_points_snapshot')->default(0);
            $table->unsignedInteger('points_earned_snapshot')->default(0);
            $table->unsignedInteger('points_spent_snapshot')->default(0);
            $table->unsignedInteger('redeemed_coupons_snapshot')->default(0);
            $table->timestamps();

            $table->unique(['ai_cluster_run_id', 'customer_id']);
            $table->index(['ai_cluster_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        // This removes the ai_cluster_customers table created in up().
        Schema::dropIfExists('ai_cluster_customers');
    }
};

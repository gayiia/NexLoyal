<?php

// This migration creates the ai_cluster_awards table for AI rewards.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table stores award definitions tied to clusters.
        Schema::create('ai_cluster_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->unsignedInteger('points_amount')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['ai_cluster_id', 'status']);
        });
    }

    public function down(): void
    {
        // This removes the ai_cluster_awards table created in up().
        Schema::dropIfExists('ai_cluster_awards');
    }
};

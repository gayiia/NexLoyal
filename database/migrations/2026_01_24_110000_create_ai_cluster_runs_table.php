<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_cluster_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_customers')->default(0);
            $table->unsignedInteger('total_clusters')->default(0);
            $table->decimal('silhouette_score', 6, 4)->nullable();
            $table->json('params')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cluster_runs');
    }
};

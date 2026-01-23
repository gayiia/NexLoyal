<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_award_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cluster_award_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_cluster_award_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_award_issuances');
    }
};

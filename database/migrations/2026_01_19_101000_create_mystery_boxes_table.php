<?php

// This migration creates the mystery_boxes table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table stores mystery box configuration and scheduling.
        Schema::create('mystery_boxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('name');
            $table->json('tiers');
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('claim_rule')->default('ONCE_PER_DAY');
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        // This removes the mystery_boxes table created in up().
        Schema::dropIfExists('mystery_boxes');
    }
};

<?php

// This migration creates the mystery_box_items table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table links mystery boxes to coupon rewards with weights.
        Schema::create('mystery_box_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mystery_box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('weight')->default(1);
            $table->timestamps();

            $table->unique(['mystery_box_id', 'coupon_id']);
        });
    }

    public function down(): void
    {
        // This removes the mystery_box_items table created in up().
        Schema::dropIfExists('mystery_box_items');
    }
};

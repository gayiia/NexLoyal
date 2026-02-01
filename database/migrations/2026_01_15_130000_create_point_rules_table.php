<?php

// This migration creates the point_rules table for configurable rewards.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table stores point reward settings.
        Schema::create('point_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('birthday_points')->default(0);
            $table->unsignedInteger('profile_completion_points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // This removes the point_rules table created in up().
        Schema::dropIfExists('point_rules');
    }
};

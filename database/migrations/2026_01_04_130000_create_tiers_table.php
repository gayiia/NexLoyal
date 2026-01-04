<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color', 7);
            $table->unsignedInteger('min_points');
            $table->unsignedInteger('max_points');
            $table->decimal('single_point_value', 10, 2);
            $table->text('description')->nullable();
            $table->string('status')->default('inactive');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiers');
    }
};

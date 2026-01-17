<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists('mystery_box_items');
    }
};

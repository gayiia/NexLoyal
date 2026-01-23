<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('avg_order_value', 12, 2)->default(0);
            $table->unsignedInteger('redeemed_coupons')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('points_spent')->default(0);
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->unsignedInteger('points_pending')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('days_since_last_order')->nullable();
            $table->json('features')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_features');
    }
};

<?php

// This migration creates the customer_coupons table for redemptions.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table records coupon redemptions for customers.
        Schema::create('customer_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_spent')->default(0);
            $table->string('code')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        // This removes the customer_coupons table created in up().
        Schema::dropIfExists('customer_coupons');
    }
};

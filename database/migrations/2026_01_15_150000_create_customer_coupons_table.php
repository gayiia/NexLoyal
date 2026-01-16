<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists('customer_coupons');
    }
};

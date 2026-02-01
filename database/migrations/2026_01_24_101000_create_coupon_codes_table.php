<?php

// This migration creates the coupon_codes table for issued codes.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table tracks unique codes and their issuance status.
        Schema::create('coupon_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('available');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'status']);
        });
    }

    public function down(): void
    {
        // This removes the coupon_codes table created in up().
        Schema::dropIfExists('coupon_codes');
    }
};

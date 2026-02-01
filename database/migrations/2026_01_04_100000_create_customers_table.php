<?php

// This migration creates the customers table sourced from Shopify data.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table stores customer identity, status, and Shopify metrics.
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('shopify_id')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // This removes the customers table created in up().
        Schema::dropIfExists('customers');
    }
};

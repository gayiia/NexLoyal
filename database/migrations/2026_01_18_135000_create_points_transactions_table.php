<?php

// This migration creates the points_transactions ledger table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table records all points earn/spend events for customers.
        Schema::create('points_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('status')->default('APPROVED');
            $table->string('source')->default('RULE');
            $table->string('type')->default('EARN');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('event_key');
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'event_key']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        // This removes the points_transactions table created in up().
        Schema::dropIfExists('points_transactions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_poll_votes')) {
            return;
        }

        Schema::create('chat_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreignId('chat_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('chat_poll_options')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('voted_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_poll_id', 'customer_id']);
            $table->index(['chat_poll_id', 'option_id']);
            $table->index(['store_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_votes');
    }
};

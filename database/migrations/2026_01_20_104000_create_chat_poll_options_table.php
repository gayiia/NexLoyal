<?php

// This migration creates the chat_poll_options table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids recreating the table if it already exists.
        if (Schema::hasTable('chat_poll_options')) {
            return;
        }

        // This table stores poll option labels and ordering.
        Schema::create('chat_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['chat_poll_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        // This removes the chat_poll_options table created in up().
        Schema::dropIfExists('chat_poll_options');
    }
};

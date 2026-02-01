<?php

// This migration creates the chat_polls table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids recreating the table if it already exists.
        if (Schema::hasTable('chat_polls')) {
            return;
        }

        // This table stores poll settings attached to messages.
        Schema::create('chat_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->boolean('allow_multiple')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->unique('chat_message_id');
        });
    }

    public function down(): void
    {
        // This removes the chat_polls table created in up().
        Schema::dropIfExists('chat_polls');
    }
};

<?php

// This migration creates the chat_messages table for exclusive chat.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids recreating the table if it already exists.
        if (Schema::hasTable('chat_messages')) {
            return;
        }

        // This table stores message content and visibility rules.
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('type');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('tier_visibility')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        // This removes the chat_messages table created in up().
        Schema::dropIfExists('chat_messages');
    }
};

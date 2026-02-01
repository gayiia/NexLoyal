<?php

// This migration creates the chat_attachments table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids recreating the table if it already exists.
        if (Schema::hasTable('chat_attachments')) {
            return;
        }

        // This table stores attachment metadata for chat messages.
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->string('file_url');
            $table->string('file_type')->default('IMAGE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['chat_message_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        // This removes the chat_attachments table created in up().
        Schema::dropIfExists('chat_attachments');
    }
};

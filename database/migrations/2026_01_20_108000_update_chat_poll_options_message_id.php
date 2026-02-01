<?php

// This migration aligns legacy chat_poll_options schema for compatibility.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids running when the base table is missing.
        if (!Schema::hasTable('chat_poll_options')) {
            return;
        }

        // This ensures chat_poll_id exists for newer relationships.
        if (!Schema::hasColumn('chat_poll_options', 'chat_poll_id')) {
            Schema::table('chat_poll_options', function (Blueprint $table) {
                $table->unsignedBigInteger('chat_poll_id')->nullable();
            });
        }

        // This relaxes legacy message_id to nullable when it exists.
        if (Schema::hasColumn('chat_poll_options', 'message_id')) {
            try {
                DB::statement('ALTER TABLE `chat_poll_options` MODIFY `message_id` BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // If the column is already nullable or differs, ignore.
            }
        }
    }

    public function down(): void
    {
        // No-op: only relaxes legacy schema for compatibility.
    }
};

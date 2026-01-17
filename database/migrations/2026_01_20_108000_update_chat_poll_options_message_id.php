<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_poll_options')) {
            return;
        }

        if (!Schema::hasColumn('chat_poll_options', 'chat_poll_id')) {
            Schema::table('chat_poll_options', function (Blueprint $table) {
                $table->unsignedBigInteger('chat_poll_id')->nullable();
            });
        }

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

<?php

// This migration backfills missing columns on chat_poll_options.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids running when the base table is missing.
        if (!Schema::hasTable('chat_poll_options')) {
            return;
        }

        // These flags determine which columns still need to be added.
        $addPollId = !Schema::hasColumn('chat_poll_options', 'chat_poll_id');
        $addLabel = !Schema::hasColumn('chat_poll_options', 'label');
        $addSortOrder = !Schema::hasColumn('chat_poll_options', 'sort_order');
        $addCreatedAt = !Schema::hasColumn('chat_poll_options', 'created_at');
        $addUpdatedAt = !Schema::hasColumn('chat_poll_options', 'updated_at');

        Schema::table('chat_poll_options', function (Blueprint $table) use (
            $addPollId,
            $addLabel,
            $addSortOrder,
            $addCreatedAt,
            $addUpdatedAt
        ) {
            // Each column is added only if missing to prevent migration errors.
            if ($addPollId) {
                $table->unsignedBigInteger('chat_poll_id')->nullable();
            }
            if ($addLabel) {
                $table->string('label')->nullable();
            }
            if ($addSortOrder) {
                $table->unsignedInteger('sort_order')->default(0);
            }
            if ($addCreatedAt) {
                $table->timestamp('created_at')->nullable();
            }
            if ($addUpdatedAt) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: only adds missing columns for existing installations.
    }
};

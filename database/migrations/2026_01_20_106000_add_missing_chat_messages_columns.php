<?php

// This migration backfills missing columns on chat_messages for older installs.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids running when the base table is missing.
        if (!Schema::hasTable('chat_messages')) {
            return;
        }

        // These flags determine which columns still need to be added.
        $addStoreId = !Schema::hasColumn('chat_messages', 'store_id');
        $addType = !Schema::hasColumn('chat_messages', 'type');
        $addTitle = !Schema::hasColumn('chat_messages', 'title');
        $addBody = !Schema::hasColumn('chat_messages', 'body');
        $addTierVisibility = !Schema::hasColumn('chat_messages', 'tier_visibility');
        $addSentAt = !Schema::hasColumn('chat_messages', 'sent_at');
        $addCreatedAt = !Schema::hasColumn('chat_messages', 'created_at');
        $addUpdatedAt = !Schema::hasColumn('chat_messages', 'updated_at');

        Schema::table('chat_messages', function (Blueprint $table) use (
            $addStoreId,
            $addType,
            $addTitle,
            $addBody,
            $addTierVisibility,
            $addSentAt,
            $addCreatedAt,
            $addUpdatedAt
        ) {
            // Each column is added only if missing to prevent migration errors.
            if ($addStoreId) {
                $table->unsignedBigInteger('store_id')->nullable();
            }
            if ($addType) {
                $table->string('type')->nullable();
            }
            if ($addTitle) {
                $table->string('title')->nullable();
            }
            if ($addBody) {
                $table->text('body')->nullable();
            }
            if ($addTierVisibility) {
                $table->json('tier_visibility')->nullable();
            }
            if ($addSentAt) {
                $table->timestamp('sent_at')->nullable();
            }
            if ($addCreatedAt) {
                $table->timestamp('created_at')->nullable();
            }
            if ($addUpdatedAt) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        // This adds the composite index if the columns exist and the index is missing.
        $canIndex = Schema::hasColumn('chat_messages', 'store_id')
            && Schema::hasColumn('chat_messages', 'sent_at');

        if ($canIndex) {
            $existing = DB::select(
                "SHOW INDEX FROM `chat_messages` WHERE Key_name = 'chat_messages_store_id_sent_at_index'"
            );
            if (!$existing) {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->index(['store_id', 'sent_at']);
                });
            }
        }
    }

    public function down(): void
    {
        // No-op: we only add missing columns for existing installations.
    }
};

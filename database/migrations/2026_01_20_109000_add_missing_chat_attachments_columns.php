<?php

// This migration backfills missing columns and data for chat_attachments.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // This avoids running when the base table is missing.
        if (!Schema::hasTable('chat_attachments')) {
            return;
        }

        // These flags determine which columns still need to be added.
        $addMessageId = !Schema::hasColumn('chat_attachments', 'chat_message_id');
        $addFileUrl = !Schema::hasColumn('chat_attachments', 'file_url');
        $addFileType = !Schema::hasColumn('chat_attachments', 'file_type');
        $addSortOrder = !Schema::hasColumn('chat_attachments', 'sort_order');
        $addCreatedAt = !Schema::hasColumn('chat_attachments', 'created_at');
        $addUpdatedAt = !Schema::hasColumn('chat_attachments', 'updated_at');

        Schema::table('chat_attachments', function (Blueprint $table) use (
            $addMessageId,
            $addFileUrl,
            $addFileType,
            $addSortOrder,
            $addCreatedAt,
            $addUpdatedAt
        ) {
            // Each column is added only if missing to prevent migration errors.
            if ($addMessageId) {
                $table->unsignedBigInteger('chat_message_id')->nullable();
            }
            if ($addFileUrl) {
                $table->string('file_url')->nullable();
            }
            if ($addFileType) {
                $table->string('file_type')->nullable();
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

        // These updates migrate legacy column data into the new columns.
        if (Schema::hasColumn('chat_attachments', 'message_id')) {
            DB::statement('UPDATE `chat_attachments` SET `chat_message_id` = `message_id` WHERE `chat_message_id` IS NULL AND `message_id` IS NOT NULL');
        }

        if (Schema::hasColumn('chat_attachments', 'url')) {
            DB::statement('UPDATE `chat_attachments` SET `file_url` = `url` WHERE `file_url` IS NULL AND `url` IS NOT NULL');
        }

        if (Schema::hasColumn('chat_attachments', 'file_path')) {
            $rows = DB::table('chat_attachments')
                ->select('id', 'file_path')
                ->whereNull('file_url')
                ->whereNotNull('file_path')
                ->get();

            // This converts stored file paths into public URLs.
            foreach ($rows as $row) {
                $path = (string) $row->file_path;
                $url = Str::startsWith($path, ['http://', 'https://'])
                    ? $path
                    : Storage::disk('public')->url($path);
                DB::table('chat_attachments')
                    ->where('id', $row->id)
                    ->update(['file_url' => $url]);
            }
        }

        if (Schema::hasColumn('chat_attachments', 'path')) {
            $rows = DB::table('chat_attachments')
                ->select('id', 'path')
                ->whereNull('file_url')
                ->whereNotNull('path')
                ->get();

            // This converts legacy path values into public URLs.
            foreach ($rows as $row) {
                $path = (string) $row->path;
                $url = Str::startsWith($path, ['http://', 'https://'])
                    ? $path
                    : Storage::disk('public')->url($path);
                DB::table('chat_attachments')
                    ->where('id', $row->id)
                    ->update(['file_url' => $url]);
            }
        }
    }

    public function down(): void
    {
        // No-op: only adds missing columns for existing installations.
    }
};

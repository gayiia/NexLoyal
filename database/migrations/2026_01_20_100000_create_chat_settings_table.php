<?php

// This migration creates settings for the exclusive chat feature.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table stores chat enablement and allowed tiers per store.
        Schema::create('chat_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable()->unique();
            $table->boolean('enabled')->default(false);
            $table->json('allowed_tiers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // This removes the chat_settings table created in up().
        Schema::dropIfExists('chat_settings');
    }
};

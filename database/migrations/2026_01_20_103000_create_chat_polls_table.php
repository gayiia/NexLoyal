<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_polls')) {
            return;
        }

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
        Schema::dropIfExists('chat_polls');
    }
};

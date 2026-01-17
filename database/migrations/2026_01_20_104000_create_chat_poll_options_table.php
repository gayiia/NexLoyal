<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_poll_options')) {
            return;
        }

        Schema::create('chat_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['chat_poll_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_options');
    }
};

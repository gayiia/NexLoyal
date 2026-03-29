<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('webhook_key', 80);
            $table->string('topic', 120)->nullable();
            $table->string('request_path');
            $table->string('request_url')->nullable();
            $table->string('delivery_state', 40);
            $table->unsignedSmallInteger('response_status');
            $table->boolean('hmac_valid')->nullable();
            $table->string('shop_domain')->nullable();
            $table->string('shopify_webhook_id')->nullable();
            $table->string('shopify_event_id')->nullable();
            $table->json('request_headers')->nullable();
            $table->longText('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_key', 'created_at']);
            $table->index(['topic', 'created_at']);
            $table->index(['delivery_state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_webhook_logs');
    }
};

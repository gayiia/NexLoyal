<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->string('value_type');
            $table->decimal('value', 10, 2)->nullable();
            $table->unsignedInteger('points_value');
            $table->foreignId('tier_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->string('status')->index();
            $table->string('code')->nullable();
            $table->string('shopify_price_rule_id')->nullable();
            $table->string('shopify_discount_code_id')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('buy_product_ids')->nullable();
            $table->json('get_product_ids')->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->timestamps();

            $table->index(['type', 'value_type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

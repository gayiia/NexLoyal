<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('code')->nullable()->after('status');
            $table->string('shopify_price_rule_id')->nullable()->after('code');
            $table->string('shopify_discount_code_id')->nullable()->after('shopify_price_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'shopify_price_rule_id',
                'shopify_discount_code_id',
            ]);
        });
    }
};

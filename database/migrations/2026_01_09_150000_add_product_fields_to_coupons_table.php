<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('status');
            $table->json('buy_product_ids')->nullable()->after('product_ids');
            $table->json('get_product_ids')->nullable()->after('buy_product_ids');
            $table->unsignedInteger('buy_quantity')->nullable()->after('get_product_ids');
            $table->unsignedInteger('get_quantity')->nullable()->after('buy_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn([
                'product_ids',
                'buy_product_ids',
                'get_product_ids',
                'buy_quantity',
                'get_quantity',
            ]);
        });
    }
};

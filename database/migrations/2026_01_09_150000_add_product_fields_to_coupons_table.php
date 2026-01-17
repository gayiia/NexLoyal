<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('coupons', 'product_ids')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->json('product_ids')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('coupons', 'buy_product_ids')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->json('buy_product_ids')->nullable()->after('product_ids');
            });
        }
        if (!Schema::hasColumn('coupons', 'get_product_ids')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->json('get_product_ids')->nullable()->after('buy_product_ids');
            });
        }
        if (!Schema::hasColumn('coupons', 'buy_quantity')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->unsignedInteger('buy_quantity')->nullable()->after('get_product_ids');
            });
        }
        if (!Schema::hasColumn('coupons', 'get_quantity')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->unsignedInteger('get_quantity')->nullable()->after('buy_quantity');
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'product_ids',
            'buy_product_ids',
            'get_product_ids',
            'buy_quantity',
            'get_quantity',
        ];

        $toDrop = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('coupons', $column)) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop) {
            Schema::table('coupons', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};

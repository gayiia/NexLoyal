<?php

// This migration adds Shopify discount identifiers to coupons.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These checks add Shopify linkage columns when missing.
        if (!Schema::hasColumn('coupons', 'code')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->string('code')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('coupons', 'shopify_price_rule_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->string('shopify_price_rule_id')->nullable()->after('code');
            });
        }
        if (!Schema::hasColumn('coupons', 'shopify_discount_code_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->string('shopify_discount_code_id')->nullable()->after('shopify_price_rule_id');
            });
        }
    }

    public function down(): void
    {
        // This removes the Shopify columns if they exist.
        $columns = [
            'code',
            'shopify_price_rule_id',
            'shopify_discount_code_id',
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

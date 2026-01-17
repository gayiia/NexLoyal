<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_coupons', function (Blueprint $table) {
            $table->string('source')->default('REDEEM')->after('status');
            $table->foreignId('mystery_box_id')->nullable()->constrained('mystery_boxes')->nullOnDelete();
            $table->index(['source', 'mystery_box_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_coupons', function (Blueprint $table) {
            $table->dropIndex(['source', 'mystery_box_id']);
            $table->dropConstrainedForeignId('mystery_box_id');
            $table->dropColumn('source');
        });
    }
};

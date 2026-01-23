<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_cluster_award_customers')) {
            $hasIndex = $this->hasIndex('ai_cluster_award_customers', 'ai_award_customer_unique');
            if (!$hasIndex) {
                Schema::table('ai_cluster_award_customers', function (Blueprint $table) {
                    $table->unique(['ai_cluster_award_id', 'customer_id'], 'ai_award_customer_unique');
                });
            }
            return;
        }

        Schema::create('ai_cluster_award_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cluster_award_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_cluster_award_id', 'customer_id'], 'ai_award_customer_unique');
            $table->index(['ai_cluster_award_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cluster_award_customers');
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $index]
        );

        return !empty($result);
    }
};

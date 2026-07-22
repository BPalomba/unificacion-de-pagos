<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('unified_payment_id')
                ->references('id')->on('unified_payments')
                ->nullOnDelete();

            $table->foreign('payment_review_group_id')
                ->references('id')->on('payment_review_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['unified_payment_id']);
            $table->dropForeign(['payment_review_group_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('penalty_rate', 8, 4)->default(0)->after('processing_fee_rate');
            $table->unsignedSmallInteger('grace_period_days')->default(0)->after('penalty_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['penalty_rate', 'grace_period_days']);
        });
    }
};

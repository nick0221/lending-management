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
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->decimal('penalty_accrued', 15, 2)->default(0)->after('balance');
            $table->unsignedInteger('penalty_accrued_periods')->default(0)->after('penalty_accrued');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn(['penalty_accrued', 'penalty_accrued_periods']);
        });
    }
};

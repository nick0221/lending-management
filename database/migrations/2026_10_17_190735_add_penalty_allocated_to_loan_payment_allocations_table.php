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
        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->decimal('penalty_allocated', 15, 2)->default(0)->after('fee_allocated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->dropColumn('penalty_allocated');
        });
    }
};

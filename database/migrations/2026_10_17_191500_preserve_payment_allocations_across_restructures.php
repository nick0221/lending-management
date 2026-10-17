<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payment allocations are an immutable audit trail. Restructuring a loan
     * replaces its schedule, so the allocation keeps the installment sequence
     * it was applied to and simply detaches from the deleted installment row.
     */
    public function up(): void
    {
        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->nullable()->after('installment_id');
        });

        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['installment_id']);
            $table->foreignId('installment_id')->nullable()->change();
            $table->foreign('installment_id')->references('id')->on('loan_installments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['installment_id']);
            $table->foreignId('installment_id')->nullable(false)->change();
            $table->foreign('installment_id')->references('id')->on('loan_installments')->cascadeOnDelete();
            $table->dropColumn('sequence');
        });
    }
};

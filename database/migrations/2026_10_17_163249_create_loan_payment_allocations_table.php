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
        Schema::create('loan_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('loan_installments')->cascadeOnDelete();
            $table->decimal('principal_allocated', 15, 2);
            $table->decimal('interest_allocated', 15, 2);
            $table->decimal('fee_allocated', 15, 2);
            $table->timestamps();
            $table->index(['loan_id', 'installment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payment_allocations');
    }
};

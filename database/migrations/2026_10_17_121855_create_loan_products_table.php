<?php

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('interest_method')->default(InterestMethod::Flat->value);
            $table->decimal('interest_rate', 8, 4);
            $table->string('repayment_frequency')->default(RepaymentFrequency::Monthly->value);
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->unsignedInteger('min_term_months');
            $table->unsignedInteger('max_term_months');
            $table->decimal('processing_fee_rate', 8, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};

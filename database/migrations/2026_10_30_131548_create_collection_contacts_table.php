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
        Schema::create('collection_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32);
            $table->string('outcome', 32);
            $table->timestamp('contacted_at');
            $table->text('notes')->nullable();
            $table->decimal('promised_amount', 15, 2)->nullable();
            $table->date('promised_at')->nullable();
            $table->date('follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'contacted_at']);
            $table->index('follow_up_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_contacts');
    }
};

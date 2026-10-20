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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type');
            $table->string('channel')->default('mail');
            $table->foreignId('borrower_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['type', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();
            $table->string('organisation_name', 120);
            $table->string('currency_symbol', 8)->default('$');
            $table->decimal('default_penalty_rate', 8, 4)->default(0);
            $table->unsignedSmallInteger('default_grace_period_days')->default(0);
            $table->unsignedSmallInteger('money_rate_limit')->default(30);
            $table->unsignedSmallInteger('reports_rate_limit')->default(10);
            $table->unsignedSmallInteger('reminders_rate_limit')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

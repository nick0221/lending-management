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
        Schema::table('borrowers', function (Blueprint $table) {
            $table->boolean('notify_due_soon')->default(true)->after('notes');
            $table->boolean('notify_overdue')->default(true)->after('notify_due_soon');
            $table->boolean('notify_receipts')->default(true)->after('notify_overdue');
            $table->boolean('notify_statements')->default(true)->after('notify_receipts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn([
                'notify_due_soon',
                'notify_overdue',
                'notify_receipts',
                'notify_statements',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('status')->default('queued')->after('channel');
            $table->text('error')->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->change();
        });

        DB::table('notification_logs')->update(['status' => 'sent']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_logs')->whereNull('sent_at')->update(['sent_at' => now()]);

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropColumn(['status', 'error']);
            $table->timestamp('sent_at')->nullable(false)->change();
        });
    }
};

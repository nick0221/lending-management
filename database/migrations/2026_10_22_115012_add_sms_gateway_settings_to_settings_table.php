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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('sms_driver', 20)->nullable()->after('reminders_rate_limit');
            $table->string('sms_from', 64)->nullable()->after('sms_driver');
            $table->string('sms_http_url', 2048)->nullable()->after('sms_from');
            $table->string('sms_http_method', 10)->nullable()->after('sms_http_url');
            $table->string('sms_http_format', 10)->nullable()->after('sms_http_method');
            $table->text('sms_http_token')->nullable()->after('sms_http_format');
            $table->string('sms_http_token_header', 100)->nullable()->after('sms_http_token');
            $table->string('sms_http_token_prefix', 100)->nullable()->after('sms_http_token_header');
            $table->text('sms_http_headers')->nullable()->after('sms_http_token_prefix');
            $table->string('sms_http_to_field', 50)->nullable()->after('sms_http_headers');
            $table->string('sms_http_message_field', 50)->nullable()->after('sms_http_to_field');
            $table->string('sms_http_from_field', 50)->nullable()->after('sms_http_message_field');
            $table->unsignedSmallInteger('sms_http_timeout')->nullable()->after('sms_http_from_field');
            $table->unsignedSmallInteger('sms_http_connect_timeout')->nullable()->after('sms_http_timeout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'sms_driver',
                'sms_from',
                'sms_http_url',
                'sms_http_method',
                'sms_http_format',
                'sms_http_token',
                'sms_http_token_header',
                'sms_http_token_prefix',
                'sms_http_headers',
                'sms_http_to_field',
                'sms_http_message_field',
                'sms_http_from_field',
                'sms_http_timeout',
                'sms_http_connect_timeout',
            ]);
        });
    }
};

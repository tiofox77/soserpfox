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
        Schema::table('tenant_notification_settings', function (Blueprint $table) {
            $table->string('sms_api_token')->nullable()->after('sms_from_number');
            $table->string('sms_sender_id')->nullable()->after('sms_api_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['sms_api_token', 'sms_sender_id']);
        });
    }
};

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
            $table->json('sms_notification_templates')->nullable()->after('sms_notifications');
            $table->json('email_notification_templates')->nullable()->after('email_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['sms_notification_templates', 'email_notification_templates']);
        });
    }
};

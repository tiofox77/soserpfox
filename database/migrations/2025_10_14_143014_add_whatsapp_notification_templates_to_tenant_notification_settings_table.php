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
            $table->json('whatsapp_notification_templates')->nullable()->after('whatsapp_templates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_notification_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_notification_templates');
        });
    }
};

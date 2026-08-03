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
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_settings', 'agt_notification_emails')) {
                $table->string('agt_notification_emails', 1000)->nullable()
                    ->after('agt_eac_code')
                    ->comment('CSV de emails para notificar em caso de erro AGT');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_settings', 'agt_notification_emails')) {
                $table->dropColumn('agt_notification_emails');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O LEMBRETE DA VÉSPERA das marcações da oficina (28/09/2026): cada marcação
 * é lembrada uma vez só. Volta a nulo quando a data da marcação muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_appointments') && ! Schema::hasColumn('workshop_appointments', 'reminder_sent_at')) {
            Schema::table('workshop_appointments', function (Blueprint $table) {
                $table->timestamp('reminder_sent_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workshop_appointments', 'reminder_sent_at')) {
            Schema::table('workshop_appointments', function (Blueprint $table) {
                $table->dropColumn('reminder_sent_at');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AGT v1.2 — agt_eac_code default por tenant.
 * Usado quando o documento não tem eac_code próprio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_settings', 'agt_eac_code')) {
                $table->string('agt_eac_code', 10)->nullable()
                    ->comment('Código de Actividade Económica (CAE) default do tenant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_settings', 'agt_eac_code')) {
                $table->dropColumn('agt_eac_code');
            }
        });
    }
};

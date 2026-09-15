<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O QUE CADA CLIENTE VÊ NO PORTAL — escolhido na ficha dele pela empresa.
 *
 * Nulo = o portal de sempre (facturas e eventos), para os clientes que já
 * tinham acesso não perderem nada no dia em que isto entra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoicing_clients', 'portal_modulos')) {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->json('portal_modulos')->nullable()->after('portal_access');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoicing_clients', 'portal_modulos')) {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->dropColumn('portal_modulos');
            });
        }
    }
};

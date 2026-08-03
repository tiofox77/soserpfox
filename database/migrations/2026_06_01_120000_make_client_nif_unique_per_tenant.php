<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * O NIF dos clientes era ÚNICO GLOBALMENTE, o que impedia cada tenant de ter o seu
 * próprio "Consumidor Final" (NIF 999999999) — colidia entre empresas.
 * Passa a ser único por tenant: UNIQUE (tenant_id, nif).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Remover o índice único global em `nif` (se existir)
        try {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->dropUnique('invoicing_clients_nif_unique');
            });
        } catch (\Throwable $e) {
            // índice pode ter outro nome / já ter sido removido — ignorar
        }

        // Adicionar índice único composto (tenant_id, nif), se ainda não existir
        $exists = collect(DB::select("SHOW INDEX FROM invoicing_clients"))
            ->contains(fn ($i) => $i->Key_name === 'invoicing_clients_tenant_id_nif_unique');

        if (!$exists) {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->unique(['tenant_id', 'nif'], 'invoicing_clients_tenant_id_nif_unique');
            });
        }
    }

    public function down(): void
    {
        try {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->dropUnique('invoicing_clients_tenant_id_nif_unique');
            });
        } catch (\Throwable $e) {
        }

        // Restaurar o único global (best-effort)
        try {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->unique('nif', 'invoicing_clients_nif_unique');
            });
        } catch (\Throwable $e) {
        }
    }
};

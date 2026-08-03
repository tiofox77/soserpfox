<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * accounting_moves.created_by passa a poder ser nulo.
 *
 * A coluna era NOT NULL sem valor por omissão, mas os lançamentos gerados pela
 * integração Faturação → Contabilidade não têm autor humano: nascem de um
 * observer, muitas vezes sem sessão (POS/PWA, API, jobs). Resultado: qualquer
 * tentativa de lançar automaticamente rebentava com
 * "Field 'created_by' doesn't have a default value" — e como o mapeamento
 * `invoice` também não existia (ver IntegrationMappingSeeder), o erro estava
 * escondido.
 *
 * Usa SQL directo para não depender do doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('accounting_moves', 'created_by')) {
            return;
        }

        DB::statement('ALTER TABLE `accounting_moves` MODIFY `created_by` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('accounting_moves', 'created_by')) {
            return;
        }

        // Só é possível voltar a NOT NULL se não houver lançamentos automáticos
        if (DB::table('accounting_moves')->whereNull('created_by')->exists()) {
            return;
        }

        DB::statement('ALTER TABLE `accounting_moves` MODIFY `created_by` BIGINT UNSIGNED NOT NULL');
    }
};

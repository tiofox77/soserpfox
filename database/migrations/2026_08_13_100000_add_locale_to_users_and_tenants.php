<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A língua de quem usa o sistema.
 *
 * Fase 0 do plano multi-língua (docs/PLANO-MULTILINGUA.md). Duas colunas,
 * ambas nulas por omissão porque nulo significa "herda":
 *
 *   · users.locale — a língua do ecrã é do utilizador: o caixa trabalha em
 *     francês e o contabilista em português, na mesma empresa;
 *   · tenants.locale — o ponto de partida da empresa, usado pelos
 *     utilizadores que não escolheram e, mais tarde, pelos documentos.
 *
 * A resolução (utilizador → empresa → pt) vive no middleware DefinirLingua,
 * num sítio só.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('is_active');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};

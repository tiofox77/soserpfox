<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um plano passa a poder ter tecto de documentos emitidos.
 *
 * O FOX Friendly é uma oferta: três meses com tudo aberto. Sem tecto, uma
 * empresa podia facturar o ano inteiro à borla. Passa a dar 500 documentos.
 *
 * NULL = SEM LIMITE, e é isso que protege quem já cá está: o tecto viaja na
 * SUBSCRIÇÃO, não no plano. As subscrições que já existem ficam com NULL e
 * nada muda para elas; só as novas copiam o tecto do plano no momento em que
 * nascem. É a mesma ideia do `com_oferta` — a subscrição guarda o acordo com
 * que foi feita, e uma mudança de política hoje não reescreve o que se
 * prometeu ontem.
 */
return new class extends Migration
{
    /** O que a oferta passa a dar a quem chegar de novo. */
    private const TECTO_DO_FOX = 500;

    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_documents')->nullable()->after('max_storage_mb');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('max_documentos')->nullable()->after('utilizadores_cobrados');
        });

        // A empresa pode receber MAIS do que o plano dá (a ficha concede,
        // nunca corta) — a mesma regra do limite de utilizadores.
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('max_documents')->nullable()->after('max_storage_mb');
        });

        DB::table('plans')->where('slug', 'fox-friendly')->update(['max_documents' => self::TECTO_DO_FOX]);
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('max_documents'));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn('max_documentos'));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('max_documents'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A subscrição passa a guardar COMO foi acordada, não só quanto custa.
 *
 * Até aqui o anual dava sempre 14 meses (12 pagos + 2 de oferta), o período
 * era sempre o do ciclo, e o valor era sempre o da tabela do plano. Quem
 * gere a plataforma precisa de três liberdades que a tabela não deixava:
 *
 *   · dar ou NÃO dar os dois meses de oferta;
 *   · fechar um período em dias, à medida (90, 364, 400…);
 *   · cobrar por utilizador — N utilizadores × preço, e não o preço do plano.
 *
 * Guardam-se na subscrição por uma razão: a RENOVAÇÃO tem de repetir o
 * acordo. Sem `com_oferta` na linha, uma empresa acordada sem oferta ganhava
 * os dois meses na primeira renovação, à socapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('com_oferta')->default(true)->after('billing_cycle');
            $table->unsignedInteger('dias_personalizados')->nullable()->after('com_oferta');
            $table->decimal('preco_por_utilizador', 10, 2)->nullable()->after('amount');
            $table->unsignedInteger('utilizadores_cobrados')->nullable()->after('preco_por_utilizador');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['com_oferta', 'dias_personalizados', 'preco_por_utilizador', 'utilizadores_cobrados']);
        });
    }
};

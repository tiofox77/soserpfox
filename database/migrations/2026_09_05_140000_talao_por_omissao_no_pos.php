<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * O TALÃO PASSA A SER O PAPEL POR OMISSÃO DO BALCÃO.
 *
 * A coluna nasceu ontem com `default('a4')`, e o raciocínio de então era «A4
 * imprime em qualquer impressora». Está trocado para quem usa o POS: quem está
 * ao balcão imprime talão de 80 mm, e a factura em A4 é a excepção — a venda a
 * uma empresa que a leva para a contabilidade.
 *
 * TAMBÉM SE ARRUMAM AS LINHAS QUE JÁ LÁ ESTÃO, e só as que ainda dizem 'a4'.
 * Esse valor não foi escolhido por ninguém: foi escrito pelo `default` da
 * migração de ontem em todas as empresas de uma vez. Deixá-lo intacto era
 * mudar a omissão apenas para empresas que ainda não existem, ou seja, não
 * mudar nada.
 *
 * O `down()` repõe a omissão da coluna mas NÃO devolve o valor linha a linha:
 * depois disto, a escolha de cada empresa é a que estiver gravada. Quem quiser
 * A4 muda num clique em Definições → Facturação.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoicing_settings', 'pos_formato_impressao')) {
            Schema::table('invoicing_settings', function (Blueprint $table) {
                $table->string('pos_formato_impressao', 10)
                    ->default('talao')
                    ->comment('a4 | talao — o papel em que a venda do POS sai por omissão');
            });

            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('pos_formato_impressao', 10)
                ->default('talao')
                ->comment('a4 | talao — o papel em que a venda do POS sai por omissão')
                ->change();
        });

        // Só o que ainda tem o valor que a migração de ontem lá pôs.
        $mexidas = DB::table('invoicing_settings')
            ->where('pos_formato_impressao', 'a4')
            ->update(['pos_formato_impressao' => 'talao']);

        // Fica no registo quantas empresas mudaram, para se poder responder à
        // pergunta «quantas é que isto apanhou?» sem adivinhar.
        Log::info('[Papel do POS] omissão passou a talão', ['empresas' => $mexidas]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoicing_settings', 'pos_formato_impressao')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('pos_formato_impressao', 10)
                ->default('a4')
                ->comment('a4 | talao — o papel em que a venda do POS sai por omissão')
                ->change();
        });
    }
};

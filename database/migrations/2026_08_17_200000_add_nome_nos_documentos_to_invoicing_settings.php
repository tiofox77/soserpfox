<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Que nome da empresa sai impresso nos documentos.
 *
 * Uma empresa tem dois: o nome comercial, que é o da tabuleta e o que o
 * cliente conhece, e a designação social, que é o do registo. Nem sempre são
 * o mesmo — "Farmácia Vital Saúde" pode ser "Kienga, Limitada" no papel.
 *
 * Até aqui o sistema decidia sozinho e decidia mal: a factura em PDF imprimia
 * o nome comercial e o talão do POS imprimia o fiscal, por isso a mesma
 * empresa saía com dois nomes conforme o documento. Passa a ser uma escolha
 * da empresa, e uma só para todos os documentos.
 *
 * A omissão é a designação social, que é o que se espera num documento
 * fiscal. Quem tiver o campo vazio continua a ver o nome comercial, como
 * hoje — para esses não muda nada.
 *
 * Isto é SÓ o que se imprime. O SAFT-AO e a comunicação à AGT não passam por
 * aqui: o que vai para o fisco não é uma preferência de quem usa o sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('nome_nos_documentos', 12)->default('social')->after('show_company_logo');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn('nome_nos_documentos');
        });
    }
};

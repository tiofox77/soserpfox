<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma empresa nova nasce em PRODUÇÃO, não em homologação.
 *
 * O padrão era `sandbox` — herdado de quando o sistema estava a ser
 * certificado. Mas quem se regista é uma empresa a sério, que vai facturar a
 * sério: nascer em homologação significa que os primeiros documentos vão para
 * o ambiente de testes da AGT e não contam para nada, e ninguém dá por isso
 * até alguém pedir a factura.
 *
 * NÃO SE TOCA EM QUEM JÁ EXISTE. Mudar o ambiente de uma empresa a meio é
 * trocar-lhe as chaves e o certificado por baixo dos pés — quem está em
 * homologação de propósito continua lá, e quem já está em produção também.
 * Isto muda só o valor com que uma linha NOVA nasce.
 *
 * Nascer em produção não envia nada por engano: sem as chaves do contribuinte
 * daquele ambiente, o ecrã recusa-se a sincronizar séries e diz o que falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('agt_environment')->default('production')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('agt_environment')->default('sandbox')->change();
        });
    }
};

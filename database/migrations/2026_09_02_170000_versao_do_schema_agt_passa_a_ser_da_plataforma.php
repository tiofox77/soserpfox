<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A versão do schema da AGT deixa de estar gravada em cada empresa.
 *
 * A coluna nasceu (2026-05-07) com DEFAULT '1.2', e por isso TODAS as
 * empresas têm '1.2' escrito — o que fazia do «settings ?? constante» do
 * código uma frase morta: a constante nunca ganhava. Quando a AGT recusou o
 * 1.2 em produção (2026-09-02), mudar a constante para 2.0 não mudava nada.
 *
 * Passa a valer: NULL = «a versão da plataforma» (AGTPayloadBuilder::
 * SCHEMA_VERSION); um valor escrito é uma excepção deliberada para aquela
 * empresa (p. ex. um sandbox que ainda só aceite 1.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('agt_schema_version')->nullable()->default(null)->change();
        });

        // O '1.2' que lá está não foi decisão de ninguém — foi o DEFAULT.
        DB::table('invoicing_settings')->where('agt_schema_version', '1.2')->update(['agt_schema_version' => null]);
    }

    public function down(): void
    {
        DB::table('invoicing_settings')->whereNull('agt_schema_version')->update(['agt_schema_version' => '1.2']);

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('agt_schema_version')->default('1.2')->change();
        });
    }
};

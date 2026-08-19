<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilidade pública de um plano.
 *
 * Um plano feito à medida de um cliente não pertence à montra: não deve
 * aparecer na landing, no registo, nem na grelha de planos disponíveis dos
 * outros clientes.
 *
 * Porque não se usa is_active para isso: desactivar o plano esconde-o
 * TAMBÉM das ferramentas de gestão — deixa de ser seleccionável no painel,
 * não conta nos totais e é ignorado pelo comando que sincroniza módulos.
 * O plano à medida tem de continuar ACTIVO (para a subscrição funcionar) e
 * apenas fora da montra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->boolean('is_public')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn('is_public');
        });
    }
};

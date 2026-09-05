<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sentido da actividade: recebida ('in') ou enviada ('out').
 *
 * É o que deixa a conversa de um lead ter dois lados — a mensagem do cliente à
 * esquerda, a nossa resposta à direita. As actividades normais (chamada, nota,
 * tarefa) ficam a null: não têm sentido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->string('direction', 4)->nullable()->after('type'); // in | out | null
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};

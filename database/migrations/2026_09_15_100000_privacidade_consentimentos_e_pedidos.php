<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRIVACIDADE: A PROVA DO CONSENTIMENTO, OS PEDIDOS DO TITULAR E A VISITA ANÓNIMA.
 *
 *  · `consentimentos` — o RGPD (art. 7.º) exige PODER DEMONSTRAR que a pessoa
 *    consentiu: o quê, quando, em que versão das regras. O registo aceitava os
 *    Termos numa caixa que não ficava gravada em lado nenhum. Só se acrescenta:
 *    retirar o consentimento é uma linha nova, não um apagar.
 *  · `pedidos_de_privacidade` — acesso, rectificação, apagamento, oposição:
 *    têm prazo (um mês), e um prazo sem registo não se cumpre.
 *  · `analytics_events.anonimo` — a visita de quem não aceitou estatísticas
 *    conta-se sem cookie, sem IP e sem cidade; a marca separa-a das outras.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consentimentos')) {
            Schema::create('consentimentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->uuid('visitor_id')->nullable()->index();
                $table->string('tipo', 40);          // termos, privacidade, estatisticas, marketing
                $table->boolean('aceite');
                $table->string('versao', 20);
                $table->string('origem', 40);        // registo, convite, aviso, minha_conta
                $table->string('ip', 45)->nullable(); // já truncado
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->nullable()->index();
                $table->index(['user_id', 'tipo']);
            });
        }

        if (! Schema::hasTable('pedidos_de_privacidade')) {
            Schema::create('pedidos_de_privacidade', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('nome', 150)->nullable();
                $table->string('email', 255);
                $table->string('tipo', 30);          // acesso, rectificacao, apagamento, oposicao, limitacao, portabilidade, outro
                $table->text('mensagem')->nullable();
                $table->string('estado', 20)->default('recebido'); // recebido, em_analise, respondido, recusado
                $table->text('resposta')->nullable();
                $table->timestamp('prazo_em')->nullable();
                $table->timestamp('respondido_em')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('analytics_events') && ! Schema::hasColumn('analytics_events', 'anonimo')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                $table->boolean('anonimo')->default(false)->after('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimentos');
        Schema::dropIfExists('pedidos_de_privacidade');

        if (Schema::hasColumn('analytics_events', 'anonimo')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                $table->dropColumn('anonimo');
            });
        }
    }
};

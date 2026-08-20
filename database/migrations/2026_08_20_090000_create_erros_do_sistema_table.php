<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os erros do sistema, agrupados, para o agente externo poder avisar.
 *
 * O ficheiro de log tem 2000 linhas das quais 1972 são INFO de rotina; um erro
 * a sério afoga-se lá dentro e ninguém o vê. E ninguém está a olhar para o log
 * às três da manhã.
 *
 * Isto não é uma segunda cópia do log — é o CONTRÁRIO de um log. Um log
 * escreve uma linha por ocorrência; isto guarda uma linha por PROBLEMA, com um
 * contador. Mil ocorrências do mesmo erro são uma linha com `ocorrencias=1000`,
 * e um único aviso ao agente.
 *
 * A IMPRESSÃO DIGITAL
 * -------------------
 * `fingerprint` é o que faz o agrupamento: nível + ficheiro + linha + a
 * mensagem com os números e os identificadores limpos. Sem essa limpeza,
 * "Utilizador 123 não encontrado" e "Utilizador 456 não encontrado" seriam
 * problemas diferentes, e o agente mandaria uma mensagem por cada cliente
 * afectado — que é precisamente o que se está a tentar evitar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erros_do_sistema', function (Blueprint $table) {
            $table->id();

            // Um problema, uma linha. É o índice único que o garante.
            $table->string('fingerprint', 64)->unique();

            $table->string('nivel', 12);              // error | critical | alert | emergency
            $table->text('mensagem');
            $table->string('ficheiro', 255)->nullable();
            $table->unsignedInteger('linha')->nullable();
            $table->string('excepcao', 190)->nullable();

            // Já redigido: segredos nunca entram aqui. Ver RegistoDeErros.
            $table->json('contexto')->nullable();

            // O rasto mínimo para se perceber onde dói, sem expor o pedido.
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('url', 255)->nullable();

            $table->unsignedInteger('ocorrencias')->default(1);
            $table->timestamp('primeira_vez')->nullable();
            $table->timestamp('ultima_vez')->nullable();

            // Quando o agente foi avisado. NULL = por avisar.
            $table->timestamp('notificado_em')->nullable();
            $table->unsignedSmallInteger('notificacoes')->default(0);

            // Fechado por um humano (ou pelo agente, com escopo para isso).
            $table->timestamp('resolvido_em')->nullable();
            $table->string('resolvido_por', 120)->nullable();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['resolvido_em', 'ultima_vez']);
            $table->index(['notificado_em', 'nivel']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erros_do_sistema');
    }
};

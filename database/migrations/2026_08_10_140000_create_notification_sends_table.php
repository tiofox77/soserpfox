<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registo do que já foi notificado.
 *
 * O envio agendado não tinha memória nenhuma: a cada passagem voltava a
 * procurar os registos que cumprem a condição do modelo — facturas a vencer,
 * stock em baixo — e mandava outra vez a todos. Duas vezes por dia já eram
 * dois avisos iguais; disparado pelo tráfego, seriam tantos quantas as janelas
 * do dia. Em SMS e WhatsApp isso é dinheiro da empresa a sair.
 *
 * É esta tabela que torna o disparo por tráfego seguro. Sem ela, correr mais
 * vezes não é uma melhoria — é uma avaria mais cara.
 *
 * A JANELA
 * --------
 * A unicidade inclui o DIA. Um aviso de "factura a vencer" deve poder repetir-
 * -se amanhã se continuar por pagar; o que não pode é repetir-se cinco minutos
 * depois. Um dia é a granularidade que serve os avisos que este sistema tem
 * (validade, stock, vencimentos) e é fácil de explicar a quem os recebe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_sends', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('template_id');

            // O registo que originou o aviso (factura, artigo, evento...).
            // Sem chave estrangeira de propósito: aponta para tabelas
            // diferentes conforme o módulo do modelo.
            $table->unsignedBigInteger('record_id')->nullable();

            $table->string('channel', 12);            // email | sms | whatsapp
            $table->string('recipient', 190);         // email ou telefone

            // O dia a que este envio pertence. É o que permite repetir amanhã
            // sem repetir daqui a cinco minutos.
            $table->date('window_date');

            $table->string('status', 12)->default('sent');   // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            // A regra toda numa linha: um aviso por destinatário, por canal,
            // por registo, por dia.
            $table->unique(
                ['tenant_id', 'template_id', 'record_id', 'channel', 'recipient', 'window_date'],
                'notification_sends_unico'
            );

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_sends');
    }
};

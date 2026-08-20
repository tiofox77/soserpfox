<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memória do que já foi avisado ao cliente sobre a subscrição dele.
 *
 * Sem isto não se pode avisar ninguém. Os avisos correm à boleia do tráfego —
 * dezenas de vezes por dia — e a condição que os dispara ("factura por pagar",
 * "período a acabar") continua verdadeira até alguém pagar. Sem memória, cada
 * passagem repetia tudo: em email é assédio, em SMS é dinheiro da plataforma a
 * sair a cada clique de um cliente qualquer.
 *
 * É a mesma lição de `notification_sends` (2026_08_10), e a razão é a mesma:
 * disparar por tráfego só é uma melhoria se houver memória; sem ela é uma
 * avaria mais cara.
 *
 * PORQUÊ UMA COLUNA `referencia` DE TEXTO E NÃO invoice_id/subscription_id
 * ------------------------------------------------------------------------
 * O índice único é o que garante tudo, e em MySQL NULL nunca colide com NULL:
 * duas colunas anuláveis no índice fariam com que o aviso de "plano a expirar"
 * — que não tem factura — nunca se reconhecesse a si próprio e saísse sempre.
 * Uma referência textual não-nula ('factura:123', 'subscricao:45') fecha essa
 * porta e serve os dois casos.
 *
 * A JANELA
 * --------
 * Como em `notification_sends`, a unicidade inclui o DIA. "A sua factura vence
 * em 3 dias" tem de poder repetir-se quando faltarem 1; o que não pode é
 * repetir-se cinco minutos depois. Quem quiser um aviso irrepetível dispara-o
 * de um acontecimento que só sucede uma vez (a emissão, o pagamento) em vez de
 * uma varredura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_de_subscricao', function (Blueprint $table) {
            $table->id();

            // A empresa avisada. Nunca a empresa de quem está a navegar quando
            // o aviso pega boleia do pedido dele — vem sempre da factura ou da
            // subscrição.
            $table->unsignedBigInteger('tenant_id');

            // Qual dos avisos: factura_emitida, factura_a_vencer,
            // factura_vencida, renovada, plano_a_expirar.
            $table->string('aviso', 40);

            // A que se refere: 'factura:123' ou 'subscricao:45'. Texto e não
            // duas chaves anuláveis — ver acima.
            $table->string('referencia', 40);

            $table->string('canal', 12);          // email | sms
            $table->string('destinatario', 190);  // email ou telefone já normalizado

            $table->date('window_date');

            $table->string('status', 12)->default('sent');   // sent | failed
            $table->unsignedTinyInteger('tentativas')->default(1);
            $table->text('error')->nullable();

            $table->timestamp('created_at')->nullable();

            // O coração da coisa. Reserva-se ANTES de enviar: dois pedidos em
            // simultâneo, um só aviso — quem perder a corrida apanha a violação
            // e desiste.
            $table->unique(
                ['tenant_id', 'aviso', 'referencia', 'canal', 'destinatario', 'window_date'],
                'avisos_subscricao_unico'
            );

            // Para o ecrã e para o expurgo.
            $table->index(['tenant_id', 'created_at']);
            $table->index(['aviso', 'window_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_de_subscricao');
    }
};

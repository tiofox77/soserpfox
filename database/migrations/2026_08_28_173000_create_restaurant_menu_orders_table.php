<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os pedidos feitos pelo cliente na carta online.
 *
 * PORQUE NÃO SÃO COMANDAS. Uma comanda exige turno aberto — é a regra que
 * garante que nada se vende com a caixa fechada, e é a mesma no POS de balcão
 * e no restaurante. O cliente sentado à mesa não tem turno nenhum: não é
 * utilizador do sistema, é uma pessoa com um telemóvel.
 *
 * Deixar o menu abrir comandas obrigava a furar essa regra, e um furo aberto
 * ao público é o pior sítio para o ter — qualquer pessoa com o endereço podia
 * lançar vendas numa caixa fechada.
 *
 * Então o pedido online é o que ele é de facto: um PEDIDO. Fica à espera, o
 * empregado vê-o no ecrã da sala e aceita-o — e é aí, com o turno dele aberto,
 * que nasce a comanda. Entre uma coisa e outra não se toca em stock, nem em
 * caixa, nem em nada que a contabilidade veja.
 *
 * Os artigos vão em JSON de propósito: são o que o cliente PEDIU, e não linhas
 * de uma comanda. Se o preço mudar entretanto, quem manda é o catálogo no
 * momento em que o empregado aceita — não este registo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_menu_orders', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->unsignedBigInteger('tenant_id')->index();

            // A mesa, quando o pedido veio de um QR colado a ela. Fica também
            // o CÓDIGO em texto: se a mesa for removida amanhã, o pedido
            // continua a dizer de onde veio.
            $tabela->unsignedBigInteger('table_id')->nullable();
            $tabela->string('table_code', 60)->nullable();

            $tabela->json('items');
            $tabela->decimal('estimated_total', 15, 2)->default(0);

            $tabela->string('customer_name', 120)->nullable();
            $tabela->string('customer_phone', 30)->nullable();
            $tabela->text('notes')->nullable();

            // pending → o empregado ainda não olhou
            // accepted → virou comanda (order_id diz qual)
            // dismissed → o empregado descartou-o
            $tabela->string('status', 20)->default('pending');
            $tabela->unsignedBigInteger('order_id')->nullable();
            $tabela->unsignedBigInteger('handled_by')->nullable();
            $tabela->timestamp('handled_at')->nullable();

            $tabela->timestamps();

            // O ecrã da sala pergunta sempre a mesma coisa: o que está à espera
            // nesta casa, mais recente primeiro.
            $tabela->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_orders');
    }
};

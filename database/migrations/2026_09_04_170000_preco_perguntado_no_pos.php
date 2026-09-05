<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artigos cujo preço se decide no balcão.
 *
 * PORQUE EXISTE. Há negócios em que o preço não está na ficha: um trabalho em
 * inox feito à medida, um serviço que depende do que se combina com o cliente.
 * O catálogo entra sem preço, e quem vende escreve-o na altura.
 *
 * Sem isto, o artigo entrava no carrinho a zero e a venda saía a zero — ou
 * alguém tinha de ir editar a ficha antes de cada venda.
 *
 * SÓ MUDA O POS. Na factura de venda o preço da linha sempre se escreveu à
 * mão; lá não há nada a mudar. É no POS, onde tocar no artigo o mete logo no
 * carrinho, que a pergunta tem de existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->boolean('preco_no_pos')
                ->default(false)
                ->after('price')
                ->comment('No POS, pergunta o preço ao vender em vez de usar o da ficha');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropColumn('preco_no_pos');
        });
    }
};

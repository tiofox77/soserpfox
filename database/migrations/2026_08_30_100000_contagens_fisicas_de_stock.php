<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A contagem física: contar o que está na prateleira e acertar o sistema.
 *
 * É o coração do módulo Inventário. O ciclo é sempre o mesmo:
 *
 *   1. abre-se a contagem para um armazém (a foto do sistema tira-se AÍ —
 *      congela-se o esperado, porque as vendas continuam enquanto se conta);
 *   2. escreve-se o que se contou, artigo a artigo;
 *   3. fecha-se: cada diferença vira um MOVIMENTO verdadeiro de stock
 *      (entrada ou saída, `reference_type='contagem'`) — nunca um número
 *      escrito por cima. As linhas continuam a ser a fonte de verdade.
 *
 * Uma contagem fechada é um documento: fica com quem contou, quando, e cada
 * diferença preta no branco. É a resposta a «porque é que o stock mudou?».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->string('status', 12)->default('open'); // open|closed|cancelled
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Os totais do fecho, congelados: o relatório não pode mudar
            // com preços de amanhã.
            $table->unsignedInteger('items_counted')->default(0);
            $table->unsignedInteger('items_adjusted')->default(0);
            $table->decimal('adjustment_cost', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('invoicing_stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('stock_count_id');
            $table->unsignedBigInteger('product_id');

            // O que o sistema dizia QUANDO SE ABRIU (congelado) e o que se
            // contou. A diferença fecha-se em movimento.
            $table->decimal('expected_quantity', 12, 4)->default(0);
            $table->decimal('counted_quantity', 12, 4)->nullable();

            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->unsignedBigInteger('adjustment_movement_id')->nullable();
            $table->unsignedBigInteger('counted_by')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id'], 'contagem_produto_unico');
            $table->index(['tenant_id', 'stock_count_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_stock_count_items');
        Schema::dropIfExists('invoicing_stock_counts');
    }
};

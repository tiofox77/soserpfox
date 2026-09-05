<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requisição de compra — o pedido INTERNO, antes de haver fornecedor.
 *
 * É o primeiro passo do circuito das compras: alguém de dentro da casa diz
 * «preciso disto». Não é documento comercial nem fiscal — não sai da empresa,
 * não tem preço fechado nem imposto. Só quando é aprovada é que dá origem a
 * uma encomenda, essa sim dirigida a um fornecedor.
 *
 * Por isso a linha aceita `product_id` NULO: pede-se muitas vezes uma coisa
 * que ainda não está no catálogo («um ar condicionado para a sala 2»). A
 * descrição é que é obrigatória, e sobrevive a apagar-se o artigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_requisicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('numero');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            $table->date('necessaria_em')->nullable();
            $table->enum('estado', [
                'rascunho', 'submetida', 'aprovada', 'rejeitada', 'encomendada', 'cancelada',
            ])->default('rascunho');
            $table->text('justificacao')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('decidida_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('decidida_em')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // O número é gerado por empresa (REQ-AAAA-NNNNNN): duas empresas
            // chegam ao mesmo número, por isso a unicidade é por tenant e não
            // global — ver a memória tenant-scoped-unique-indexes.
            $table->unique(['tenant_id', 'numero']);
            $table->index(['tenant_id', 'estado']);
        });

        Schema::create('compras_requisicao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('compras_requisicoes')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('invoicing_products')->onDelete('set null');
            $table->string('descricao');
            $table->decimal('quantidade', 15, 3)->default(0);
            // Quanto desta linha já foi parar a encomendas. Fecha a requisição
            // quando iguala a quantidade pedida — e deixa ver o que ficou por
            // encomendar quando se encomenda só uma parte.
            $table->decimal('quantidade_encomendada', 15, 3)->default(0);
            $table->decimal('custo_estimado', 15, 2)->nullable();
            $table->string('unidade', 20)->nullable();
            $table->text('notas')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_requisicao_itens');
        Schema::dropIfExists('compras_requisicoes');
    }
};

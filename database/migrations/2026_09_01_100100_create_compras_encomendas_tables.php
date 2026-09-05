<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encomenda ao fornecedor (ENC) — o compromisso de compra.
 *
 * É primo do orçamento de venda: documento COMERCIAL, não fiscal. Não tem
 * série da AGT nem hash SAFT-AO, não se comunica a lado nenhum. O que o torna
 * diferente de uma factura de compra é o tempo: a encomenda é o que se pediu,
 * a factura é o que se deve. Entre as duas está a recepção da mercadoria.
 *
 * O STOCK NÃO ENTRA AQUI. Encomendar não é receber — uma encomenda enviada e
 * nunca entregue não pode inflacionar o stock. A entrada dá-se na recepção,
 * por movimento, e é a `quantidade_recebida` da linha que guarda o que já
 * chegou (parcelas incluídas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_encomendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('numero');
            $table->foreignId('supplier_id')->constrained('invoicing_suppliers')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            // De onde nasceu, quando nasceu de um pedido interno. Nulo numa
            // encomenda feita directamente, que também é caso legítimo.
            $table->foreignId('requisicao_id')->nullable()->constrained('compras_requisicoes')->onDelete('set null');
            $table->date('data_encomenda');
            $table->date('entrega_prevista')->nullable();
            $table->enum('estado', [
                'rascunho', 'enviada', 'confirmada', 'parcial', 'recebida', 'cancelada',
            ])->default('rascunho');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('imposto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('moeda', 3)->default('AOA');
            $table->decimal('cambio', 10, 4)->default(1);
            $table->text('notas')->nullable();
            $table->text('condicoes')->nullable();
            // Preenchido quando a encomenda vira factura de compra. Guarda a
            // ligação nos dois sentidos sem obrigar a existir.
            $table->unsignedBigInteger('purchase_invoice_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'numero']);
            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('compras_encomenda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encomenda_id')->constrained('compras_encomendas')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('invoicing_products')->onDelete('set null');
            $table->string('descricao');
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('quantidade_recebida', 15, 3)->default(0);
            $table->decimal('preco_unitario', 15, 2)->default(0);
            $table->decimal('desconto_percent', 5, 2)->default(0);
            // A taxa guarda-se como decimal; o id fica só como pista da taxa
            // que a originou (mesma regra das linhas de orçamento), por isso
            // não leva chave estrangeira: a taxa pode mudar de nome ou sair.
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('imposto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('unidade', 20)->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_encomenda_itens');
        Schema::dropIfExists('compras_encomendas');
    }
};

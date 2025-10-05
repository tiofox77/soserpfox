<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoicing_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('invoicing_purchase_invoices')->onDelete('set null');
            $table->foreignId('supplier_id')->constrained('invoicing_suppliers')->onDelete('restrict');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            
            // Identificação
            $table->string('import_number')->unique(); // IMP/2025/0001
            $table->string('reference')->nullable(); // Referência interna
            $table->date('order_date'); // Data do pedido
            $table->date('expected_arrival_date')->nullable(); // Data prevista de chegada
            $table->date('actual_arrival_date')->nullable(); // Data real de chegada
            
            // Dados do Embarque
            $table->string('origin_country', 100); // País de origem
            $table->string('origin_port', 200)->nullable(); // Porto de origem
            $table->string('destination_port', 200)->default('Luanda'); // Porto de destino
            $table->string('shipping_company', 200)->nullable(); // Empresa de transporte
            $table->string('container_number', 100)->nullable(); // Número do contentor
            $table->string('bill_of_lading', 100)->nullable(); // BL - Bill of Lading
            $table->enum('transport_type', ['maritime', 'air', 'land'])->default('maritime');
            
            // Documento Único (DU) - Alfândega Angola
            $table->string('du_number', 100)->nullable(); // Número do DU
            $table->date('du_date')->nullable(); // Data de emissão do DU
            $table->string('du_reference', 100)->nullable(); // Referência do DU
            $table->decimal('du_declared_value', 15, 2)->default(0); // Valor declarado no DU
            $table->string('du_currency', 3)->default('USD'); // Moeda do DU
            
            // Custos e Taxas (Angola)
            $table->decimal('fob_value', 15, 2)->default(0); // Valor FOB
            $table->decimal('freight_cost', 15, 2)->default(0); // Frete
            $table->decimal('insurance_cost', 15, 2)->default(0); // Seguro
            $table->decimal('cif_value', 15, 2)->default(0); // CIF (Cost Insurance Freight)
            $table->decimal('customs_duty', 15, 2)->default(0); // Direitos Aduaneiros
            $table->decimal('consumption_tax', 15, 2)->default(0); // Imposto de Consumo
            $table->decimal('stamp_duty', 15, 2)->default(0); // Imposto de Selo
            $table->decimal('other_charges', 15, 2)->default(0); // Outras despesas
            $table->decimal('total_import_cost', 15, 2)->default(0); // Custo total da importação
            
            // Despachante Aduaneiro
            $table->string('customs_agent', 200)->nullable(); // Nome do despachante
            $table->string('customs_agent_contact', 100)->nullable(); // Contato do despachante
            $table->decimal('agent_fee', 15, 2)->default(0); // Taxa do despachante
            
            // Status e Documentação
            $table->enum('status', [
                'quotation', // Cotação
                'order_placed', // Pedido realizado
                'payment_pending', // Pagamento pendente
                'payment_confirmed', // Pagamento confirmado
                'in_transit', // Em trânsito
                'customs_pending', // Desembaraço pendente
                'customs_inspection', // Inspeção alfandegária
                'customs_cleared', // Desembaraçado
                'in_warehouse', // No armazém
                'completed', // Concluído
                'cancelled' // Cancelado
            ])->default('quotation');
            
            $table->text('notes')->nullable(); // Observações gerais
            $table->text('customs_notes')->nullable(); // Observações alfandegárias
            
            // Documentos (JSON para rastreamento)
            $table->json('documents')->nullable(); // {tipo: nome_arquivo, path, uploaded_at}
            
            // Checklist (JSON)
            $table->json('checklist')->nullable(); // Status de cada item do checklist
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('status');
            $table->index('du_number');
            $table->index('expected_arrival_date');
        });
        
        // Tabela de Itens da Importação
        Schema::create('invoicing_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('invoicing_imports')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('restrict');
            
            $table->string('product_description'); // Descrição do produto
            $table->string('hs_code', 20)->nullable(); // Código HS (Sistema Harmonizado)
            $table->decimal('quantity', 15, 3);
            $table->string('unit', 20)->default('UN');
            $table->decimal('unit_price', 15, 2); // Preço unitário
            $table->decimal('total_price', 15, 2); // Total por item
            $table->decimal('weight_kg', 10, 3)->nullable(); // Peso em kg
            $table->string('origin_country', 100)->nullable(); // País de origem do item
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('import_id');
            $table->index('product_id');
        });
        
        // Tabela de Histórico/Timeline da Importação
        Schema::create('invoicing_import_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('invoicing_imports')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('event_type', 50); // status_change, document_uploaded, comment, etc
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->text('description'); // Descrição do evento
            $table->json('metadata')->nullable(); // Dados adicionais
            
            $table->timestamps();
            
            $table->index('import_id');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_import_history');
        Schema::dropIfExists('invoicing_import_items');
        Schema::dropIfExists('invoicing_imports');
    }
};

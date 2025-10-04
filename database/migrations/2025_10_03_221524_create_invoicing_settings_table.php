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
        Schema::create('invoicing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Armazém Padrão
            $table->foreignId('default_warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            
            // Cliente Padrão (Consumidor Final)
            $table->foreignId('default_client_id')->nullable()->constrained('invoicing_clients')->onDelete('set null');
            
            // Fornecedor Padrão
            $table->foreignId('default_supplier_id')->nullable()->constrained('invoicing_suppliers')->onDelete('set null');
            
            // Moeda Padrão
            $table->string('default_currency', 3)->default('AOA')->comment('Moeda padrão (AOA, USD, EUR)');
            $table->decimal('default_exchange_rate', 10, 4)->default(1.0000)->comment('Taxa de câmbio padrão');
            
            // Método de Pagamento Padrão
            $table->string('default_payment_method', 50)->default('dinheiro')->comment('Método padrão');
            
            // Séries de Documentos
            $table->string('proforma_series', 10)->default('PRF')->comment('Série para proformas');
            $table->string('invoice_series', 10)->default('FT')->comment('Série para faturas');
            $table->string('receipt_series', 10)->default('RC')->comment('Série para recibos');
            
            // Numeração
            $table->unsignedInteger('proforma_next_number')->default(1);
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->unsignedInteger('receipt_next_number')->default(1);
            
            // IVA Padrão
            $table->decimal('default_tax_rate', 5, 2)->default(14.00)->comment('Taxa IVA padrão Angola');
            
            // IRT (Retenção)
            $table->decimal('default_irt_rate', 5, 2)->default(6.50)->comment('Taxa IRT padrão Angola');
            $table->boolean('apply_irt_services')->default(true)->comment('Aplicar IRT em serviços automaticamente');
            
            // Descontos
            $table->boolean('allow_line_discounts')->default(true)->comment('Permitir desconto por linha');
            $table->boolean('allow_commercial_discount')->default(true);
            $table->boolean('allow_financial_discount')->default(true);
            $table->decimal('max_discount_percent', 5, 2)->default(100.00)->comment('Desconto máximo permitido');
            
            // Validade de Documentos
            $table->unsignedInteger('proforma_validity_days')->default(30)->comment('Validade padrão proforma');
            $table->unsignedInteger('invoice_due_days')->default(30)->comment('Prazo pagamento padrão');
            
            // Impressão
            $table->boolean('auto_print_after_save')->default(false);
            $table->boolean('show_company_logo')->default(true);
            $table->string('invoice_footer_text', 500)->nullable();
            
            // SAFT-AO
            $table->string('saft_software_cert', 100)->nullable()->comment('Certificado software AGT');
            $table->string('saft_product_id', 100)->nullable();
            $table->string('saft_version', 20)->default('1.0.0');
            
            // Observações padrão
            $table->text('default_notes')->nullable();
            $table->text('default_terms')->nullable();
            
            $table->timestamps();
            
            // Unique por tenant
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_settings');
    }
};

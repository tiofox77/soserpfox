<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orçamento de venda (ORC).
 *
 * É primo da proforma, mas NÃO é documento fiscal: não tem série nem hash
 * SAFT-AO, não se comunica à AGT. Serve para o comercial detalhar uma proposta
 * — cada linha leva uma descrição longa do serviço — e, quando o cliente
 * aceita, converte-se numa factura de venda (essa sim, fiscal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_sales_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('quote_number');
            $table->foreignId('client_id')->constrained('invoicing_clients')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->boolean('is_service')->default(false);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('irt_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('discount_commercial', 15, 2)->default(0);
            $table->decimal('discount_financial', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('AOA');
            $table->decimal('exchange_rate', 10, 4)->default(1);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // O número é gerado por empresa (ORC-AAAA-NNNNNN): duas empresas
            // chegam ao mesmo número, por isso a unicidade é por tenant, não
            // global — ver a memória tenant-scoped-unique-indexes.
            $table->unique(['tenant_id', 'quote_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_sales_quotes');
    }
};

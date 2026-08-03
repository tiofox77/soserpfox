<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impostos ADICIONAIS por linha de documento (IEC e IS).
 *
 * A linha já tem colunas para UM imposto (tax_rate, tax_amount, tax_code…) que
 * são o IVA — é o que toda a aplicação lê hoje. A AGT, porém, exige que uma
 * linha possa acumular IVA + IEC (bebidas, tabaco, combustíveis) ou IVA + IS
 * (verbas do Imposto de Selo), e o payload leva `taxes[]` como array.
 *
 * Sem esta tabela o produto só conseguia emitir IVA: o cenário de certificação
 * com IEC/IS era possível no JSON de teste mas irreproduzível numa emissão real.
 *
 * Mantém-se o IVA nas colunas da linha (zero risco para totais, PDFs e SAFT) e
 * acrescentam-se aqui apenas os impostos extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoicing_line_taxes')) {
            return;
        }

        Schema::create('invoicing_line_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Linha a que pertence (polimórfico: itens de FT/FR, NC, ND, …)
            $table->string('line_type', 191);
            $table->unsignedBigInteger('line_id');

            // IEC | IS  (o IVA vive nas colunas da própria linha)
            $table->string('tax_type', 10);
            $table->string('tax_country_region', 10)->default('AO');
            $table->string('tax_code', 10)->default('NOR');
            $table->decimal('tax_percentage', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);

            // Referências oficiais: código pautal (IEC) ou n.º de verba (IS)
            $table->string('pautal_code', 20)->nullable();
            $table->unsignedSmallInteger('verba_no')->nullable();
            $table->string('description', 255)->nullable();

            // Isenção, quando aplicável a este imposto específico
            $table->string('tax_exemption_code', 10)->nullable();
            $table->string('tax_exemption_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['line_type', 'line_id'], 'line_taxes_line_idx');
            $table->index(['tenant_id', 'tax_type'], 'line_taxes_tenant_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_line_taxes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A oportunidade passa a saber que documento nasceu dela.
 *
 * O CRM levava o negócio até «ganho» e parava aí: quem ganhava lia «o próximo
 * passo é a proforma», saía do CRM, procurava o cliente na Facturação e
 * escrevia o valor outra vez à mão. Duas verdades sobre o mesmo negócio — a do
 * funil e a do documento — que divergem à primeira correcção. E o funil
 * conseguia dizer quanto se GANHOU, nunca quanto se COBROU.
 *
 * A marca fica na oportunidade, como nas horas dos projetos: é ela que liga o
 * negócio ao documento e é ela que impede facturar duas vezes o mesmo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_invoice_id')->nullable()->after('closed_at');
            $table->timestamp('facturada_em')->nullable()->after('sales_invoice_id');

            $table->index(['tenant_id', 'sales_invoice_id'], 'crm_opp_factura_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropIndex('crm_opp_factura_idx');
            $table->dropColumn(['sales_invoice_id', 'facturada_em']);
        });
    }
};

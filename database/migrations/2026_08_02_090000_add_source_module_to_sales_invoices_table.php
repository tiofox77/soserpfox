<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga de forma durável uma factura ao documento de negócio que a originou.
 *
 * Até agora o único vínculo era `hotel_reservations.invoice_id` (e o
 * equivalente na oficina), que é 1:1 e é sobrescrito: com dois sinais pagos, a
 * factura do primeiro ficava sem rasto nenhum — nem a partir da reserva, nem a
 * partir do documento. Sem isto não há forma segura de deduzir no check-out o
 * que já foi facturado em adiantamentos, nem de reconciliar documento↔estadia
 * numa auditoria AGT/SAFT.
 *
 * Aditiva de propósito: `hotel_reservations.invoice_id` mantém-se e passa a
 * significar "última factura emitida", continuando a alimentar a UI existente.
 *
 * NÃO usar `source_id`/`source_billing`: são campos SAFT-AO já ocupados
 * (migração 2025_10_03_204511).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->string('source_module', 30)->nullable()->after('source_billing');
            $table->string('source_reference', 50)->nullable()->after('source_module');

            // A referência NÃO é única globalmente (dois tenants podem ter
            // OS-00001): o tenant_id à cabeça do índice é obrigatório, sob pena
            // de fuga entre empresas em qualquer consulta que o use.
            $table->index(['tenant_id', 'source_module', 'source_reference'], 'idx_invoices_origem');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_origem');
            $table->dropColumn(['source_module', 'source_reference']);
        });
    }
};

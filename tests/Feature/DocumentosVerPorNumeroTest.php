<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

/**
 * `documentos:ver --numero=` junta, lado a lado, o que separa duas facturas
 * «duplicadas sozinhas»: a tentativa, o pedido, o turno, a tesouraria e o stock.
 */
class DocumentosVerPorNumeroTest extends TenantTestCase
{
    public function test_mostra_as_duas_facturas_e_as_vizinhas(): void
    {
        $serie = InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'name' => 'FR', 'document_type' => 'invoice', 'prefix' => 'FR',
            'series_code' => 'SOSFR', 'agt_series_id' => 'FR4226S99999N', 'next_number' => 1, 'is_active' => true,
            'agt_environment' => \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id)->agt_environment,
        ]);

        foreach (['003253' => 'aaaa-1', '003254' => 'bbbb-2'] as $n => $uuid) {
            SalesInvoice::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id, 'series_id' => $serie->id,
                'invoice_number' => "FR FR4226S99999N/{$n}", 'local_uuid' => $uuid, 'invoice_date' => now()->toDateString(),
                'status' => 'paid', 'subtotal' => 1000, 'tax_amount' => 0, 'total' => 1000, 'created_by' => $this->user->id,
            ]);
        }

        Artisan::call('documentos:ver', ['--numero' => 'FR4226S99999N/003254,FR4226S99999N/003253']);
        $saida = Artisan::output();

        $this->assertStringContainsString('FR FR4226S99999N/003253', $saida);
        $this->assertStringContainsString('FR FR4226S99999N/003254', $saida);
        $this->assertStringContainsString('série interna: SOSFR', $saida);
        $this->assertStringContainsString('local_uuid bbbb-2', $saida);
        $this->assertStringContainsString('stock: NENHUM movimento', $saida);
        $this->assertStringContainsString('Facturas da empresa entre', $saida);
    }
}

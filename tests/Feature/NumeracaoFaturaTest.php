<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * A fatura mostra DUAS numerações: a da série interna gravada (a que a empresa
 * reconhece e procura) e a da série registada na AGT. O número fiscal a valer
 * continua a ser o `invoice_number`; estes helpers só servem a apresentação e a
 * pesquisa.
 */
class NumeracaoFaturaTest extends TenantTestCase
{
    private function serie(array $over = []): InvoicingSeries
    {
        return InvoicingSeries::create(array_merge([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Série de Teste',
            'document_type' => 'invoice',
            'prefix'        => 'FT',
            'series_code'   => 'SOSFT' . strtoupper(substr(uniqid(), -4)),
            'next_number'   => 1,
            'is_active'     => true,
        ], $over));
    }

    private function factura(InvoicingSeries $serie, string $numero): SalesInvoice
    {
        return SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'series_id'      => $serie->id,
            'invoice_number' => $numero,
            'invoice_type'   => 'FT',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
            'status'         => 'sent',
            'subtotal' => 1000, 'net_total' => 1000,
            'tax_payable' => 140, 'total' => 1140, 'gross_total' => 1140,
        ]);
    }

    public function test_sem_registo_agt_mostra_so_a_interna(): void
    {
        $serie = $this->serie(['series_code' => 'SOSFT', 'agt_series_id' => null]);
        // Antes do registo, o próprio invoice_number é a forma interna.
        $f = $this->factura($serie, 'FT FT/000001');

        $this->assertSame('FT SOSFT/000001', $f->numeroInterno());
        $this->assertNull($f->numeroAgt(), 'sem série AGT não há número AGT');
    }

    public function test_com_registo_agt_mostra_as_duas(): void
    {
        $serie = $this->serie(['series_code' => 'SOSFTB', 'agt_series_id' => 'FT0426S72207N']);
        // Depois do registo, o invoice_number gravado usa o código da AGT.
        $f = $this->factura($serie, 'FT FT0426S72207N/000007');

        $this->assertSame('FT SOSFTB/000007', $f->numeroInterno(), 'a interna usa o series_code gravado');
        $this->assertSame('FT FT0426S72207N/000007', $f->numeroAgt(), 'a AGT usa o agt_series_id');
    }

    public function test_a_sequencia_e_a_mesma_nas_duas(): void
    {
        $serie = $this->serie(['series_code' => 'SOSFTC', 'agt_series_id' => 'FT9999X']);
        $f = $this->factura($serie, 'FT FT9999X/000042');

        $this->assertSame('FT SOSFTC/000042', $f->numeroInterno());
        $this->assertSame('FT FT9999X/000042', $f->numeroAgt());
    }
}

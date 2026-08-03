<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Services\Invoicing\SeriesCatalog;
use Tests\TenantTestCase;

/**
 * Esquema canónico das séries de documentos.
 *
 * A regra que tudo isto protege: o código da série faz parte do número que já
 * saiu para o cliente e foi comunicado à AGT. Uma série usada NÃO se renomeia.
 */
class SeriesCatalogTest extends TenantTestCase
{
    public function test_o_catalogo_cobre_os_documentos_acordados(): void
    {
        $codigos = collect(SeriesCatalog::canonico())->pluck('code')->all();

        foreach (['SOSFR', 'SOSFT', 'SOSNC', 'SOSND', 'SOSPROV', 'SOSFC', 'SOSPROC', 'SOSRC'] as $esperado) {
            $this->assertContains($esperado, $codigos);
        }
    }

    public function test_cada_tipo_de_documento_tem_um_so_codigo(): void
    {
        $tipos = collect(SeriesCatalog::canonico())->pluck('document_type');

        $this->assertSame(
            $tipos->count(),
            $tipos->unique()->count(),
            'dois códigos para o mesmo document_type tornam a escolha da série arbitrária'
        );
    }

    public function test_provisionar_cria_as_series_em_falta_e_e_idempotente(): void
    {
        InvoicingSeries::where('tenant_id', $this->tenant->id)->delete();

        $primeira = SeriesCatalog::provisionar($this->tenant->id);
        $segunda  = SeriesCatalog::provisionar($this->tenant->id);

        $this->assertSame(count(SeriesCatalog::canonico()), $primeira);
        $this->assertSame(0, $segunda, 'correr duas vezes não pode duplicar séries');
    }

    public function test_uma_serie_registada_na_agt_nao_pode_ser_renomeada(): void
    {
        $serie = InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'XPTO',
            'name' => 'Antiga', 'document_type' => 'invoice',
            'agt_series_id' => 'FT7626S9153N', 'next_number' => 1, 'is_active' => true,
        ]);

        $this->assertFalse(SeriesCatalog::podeRenomear($serie),
            'o código está no número já comunicado à AGT');
    }

    public function test_uma_serie_com_documentos_emitidos_nao_pode_ser_renomeada(): void
    {
        $serie = InvoicingSeries::where('tenant_id', $this->tenant->id)
            ->where('document_type', 'invoice')->first()
            ?? InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => 'SOSFT',
                'name' => 'Faturas', 'document_type' => 'invoice',
                'next_number' => 1, 'is_active' => true,
            ]);

        // Emitir um documento por esta série.
        app(\App\Services\Invoicing\ModuleInvoiceService::class)->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [['name' => 'X', 'quantity' => 1, 'unit_price' => 1000]],
        ]);

        $this->assertFalse(SeriesCatalog::podeRenomear($serie->fresh()),
            'o número já saiu para o cliente');
    }

    public function test_uma_serie_virgem_pode_ser_renomeada(): void
    {
        $serie = InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'ANTIGA',
            'name' => 'Antiga', 'document_type' => 'purchase',
            'next_number' => 1, 'is_active' => true,
        ]);

        $this->assertTrue(SeriesCatalog::podeRenomear($serie));
    }

    public function test_o_comando_nao_toca_em_series_usadas(): void
    {
        $usada = InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'LEGADA',
            'name' => 'Legada', 'document_type' => 'purchase',
            'next_number' => 87, 'is_active' => true,   // já numerou 86
        ]);

        $this->artisan('series:canonical', ['--tenant' => $this->tenant->id])
            ->assertSuccessful();

        $this->assertSame('LEGADA', $usada->fresh()->series_code,
            'uma série que já numerou não muda de código');
    }

    public function test_a_proforma_de_compra_tem_tipo_proprio(): void
    {
        // Sem isto, uma proforma de compra podia sair numerada na série das
        // vendas — os dois teriam document_type 'proforma'.
        $compra = SeriesCatalog::paraTipo('purchase_proforma');
        $venda  = SeriesCatalog::paraTipo('proforma');

        $this->assertNotNull($compra);
        $this->assertSame('SOSPROC', $compra['code']);
        $this->assertSame('SOSPROV', $venda['code']);
    }
}

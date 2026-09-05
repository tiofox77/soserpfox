<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * Os documentos criados no PWA saem EMITIDOS, não em rascunho.
 *
 * Gravavam-se com status 'draft' e alguém tinha de ir ao servidor carregar em
 * "Finalizar". Um documento que o balcão deu por feito ficava à espera de um
 * segundo passo que ninguém via — sem número, sem hash e sem comunicação.
 */
class DocumentosDoPwaSaoEmitidosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');
    }

    private function criar(string $tipo): array
    {
        return $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', [
            'doc_type'   => $tipo,
            'local_uuid' => 'doc_' . uniqid(),
            'items'      => [[
                'product_id'   => $this->produtoComStock(10)->id,
                'product_name' => 'Artigo',
                'quantity'     => 2,
                'unit_price'   => 1500,
                'tax_rate'     => 0,
            ]],
        ])->assertSuccessful()->json();
    }

    public function test_uma_factura_sai_com_numero_fiscal(): void
    {
        $r = $this->criar('FT');

        $this->assertNotEmpty($r['invoice_number'], 'sem número não é documento fiscal nenhum');
        $this->assertNotSame('draft', $r['status']);

        $doc = SalesInvoice::withoutGlobalScopes()->findOrFail($r['id']);

        $this->assertSame('F', $doc->invoice_status, 'tinha de ficar finalizado');
        $this->assertNotNull($doc->series_id, 'o número tem de vir de uma série');
    }

    public function test_uma_factura_recibo_sai_paga(): void
    {
        $r = $this->criar('FR');

        $doc = SalesInvoice::withoutGlobalScopes()->findOrFail($r['id']);

        // Uma FR é paga no acto; uma FT fica a aguardar.
        $this->assertSame('paid', $doc->status);
    }

    public function test_uma_factura_fica_a_aguardar_pagamento(): void
    {
        $doc = SalesInvoice::withoutGlobalScopes()->findOrFail($this->criar('FT')['id']);

        $this->assertSame('pending', $doc->status);
    }

    public function test_dois_documentos_nunca_levam_o_mesmo_numero(): void
    {
        $um = $this->criar('FT')['invoice_number'];
        $dois = $this->criar('FT')['invoice_number'];

        $this->assertNotSame($um, $dois);
    }

    public function test_reenviar_o_mesmo_documento_nao_o_duplica(): void
    {
        // A rede oscila e o aparelho reenvia o que não teve resposta. Emitir
        // duas vezes seria emitir dois documentos fiscais para a mesma venda.
        $uuid = 'doc_repetido_' . uniqid();

        $carga = [
            'doc_type'   => 'FT',
            'local_uuid' => $uuid,
            'items'      => [[
                'product_id'   => $this->produtoComStock(10)->id,
                'product_name' => 'Artigo',
                'quantity'     => 1,
                'unit_price'   => 1000,
                'tax_rate'     => 0,
            ]],
        ];

        $um = $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', $carga)->json();
        $documento = SalesInvoice::withoutGlobalScopes()->findOrFail($um['id']);
        $counter = \App\Models\Invoicing\InvoicingSeries::findOrFail($documento->series_id)->next_number;
        $dois = $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', $carga)->json();

        $this->assertSame($um['id'], $dois['id']);
        $this->assertSame($um['invoice_number'], $dois['invoice_number']);
        $this->assertSame($counter, \App\Models\Invoicing\InvoicingSeries::findOrFail($documento->series_id)->next_number);
        $this->assertSame(1, SalesInvoice::withoutGlobalScopes()->where('local_uuid', $uuid)->count());
    }

    public function test_o_documento_leva_as_linhas(): void
    {
        $doc = SalesInvoice::withoutGlobalScopes()->findOrFail($this->criar('FT')['id']);

        $this->assertCount(1, $doc->items);

        // O liquido e 2 x 1500; o imposto quem o decide e o SERVIDOR, pelo
        // regime da empresa, e nao o que o aparelho mandou na carga. E por
        // isso que o total pode nao ser 3000: aqui a empresa liquida IVA.
        $this->assertEquals(3000, (float) $doc->subtotal);
        $this->assertEquals(
            (float) $doc->subtotal + (float) $doc->tax_amount,
            (float) $doc->total,
            'o total tem de ser o liquido mais o imposto apurado'
        );
    }

    public function test_proforma_repetida_conserva_numero_e_registo(): void
    {
        $carga = ['doc_type' => 'proforma', 'local_uuid' => 'pf-repeat-' . uniqid(),
            'items' => [['product_id' => $this->produtoComStock(10)->id,
                'product_name' => 'Artigo', 'quantity' => 1, 'unit_price' => 1000]]];
        $one = $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', $carga)->assertSuccessful()->json();
        $two = $this->postJson('/api/v1/invoicing/drafts', $carga)->assertSuccessful()->json();
        $this->assertSame($one['id'], $two['id']);
        $this->assertSame($one['proforma_number'], $two['proforma_number']);
        $this->assertTrue($two['duplicated']);
    }

    public function test_contador_muito_atrasado_continua_depois_do_maior_sem_preencher_buracos(): void
    {
        $doc = SalesInvoice::withoutGlobalScopes()->findOrFail($this->criar('FT')['id']);
        $serie = \App\Models\Invoicing\InvoicingSeries::findOrFail($doc->series_id);
        // Fixture only: simulate a historic high number and a stale counter.
        \Illuminate\Support\Facades\DB::table('invoicing_sales_invoices')->where('id', $doc->id)
            ->update(['invoice_number' => $serie->formatNumber(50000)]);
        $serie->update(['next_number' => 1]);
        $this->assertSame($serie->formatNumber(50001), $serie->getNextNumber());
        $this->assertSame($serie->formatNumber(50002), $serie->getNextNumber());
    }

    public function test_transaccao_falhada_nao_consume_o_numero(): void
    {
        $doc = SalesInvoice::withoutGlobalScopes()->findOrFail($this->criar('FT')['id']);
        $serie = \App\Models\Invoicing\InvoicingSeries::findOrFail($doc->series_id);
        $expected = $serie->next_number;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($serie) {
                $serie->getNextNumber();
                throw new \RuntimeException('simulated failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }
        $this->assertSame($expected, $serie->fresh()->next_number);
    }
}

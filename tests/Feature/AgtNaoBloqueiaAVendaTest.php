<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\EmissorFiscal;
use Tests\TenantTestCase;

/**
 * A AGT nunca é chamada durante a venda.
 *
 * O submitToAGT() faz a chamada de rede na hora. No POS isso é o operador a
 * esperar pela AGT com o cliente à frente — e uma AGT lenta transforma-se em
 * vendas que parecem encravadas.
 */
class AgtNaoBloqueiaAVendaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');

        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['agt_auto_submit' => true]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);
    }

    private function documento(): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date'   => now(),
            'status'         => 'pending',
            'total'          => 1000,
            'created_by'     => $this->user->id,
        ]);
    }

    public function test_a_comunicacao_fica_em_fila_e_nao_e_enviada_na_hora(): void
    {
        $doc = $this->documento();

        app(EmissorFiscal::class)->comunicar($doc, $this->tenant->id);

        $submissao = AGTSubmission::where('tenant_id', $this->tenant->id)
            ->where('document_id', $doc->id)->first();

        $this->assertNotNull($submissao, 'tinha de ficar em fila');
        $this->assertSame(AGTSubmission::STATUS_PENDING, $submissao->status);
        $this->assertNull($submissao->submitted_at, 'não podia já ter sido enviada');
    }

    public function test_nao_se_poe_em_fila_duas_vezes(): void
    {
        $doc = $this->documento();
        $emissor = app(EmissorFiscal::class);

        $emissor->comunicar($doc, $this->tenant->id);
        $emissor->comunicar($doc, $this->tenant->id);

        $this->assertSame(1, AGTSubmission::where('document_id', $doc->id)->count());
    }

    public function test_sem_comunicacao_automatica_nao_entra_na_fila(): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['agt_auto_submit' => false]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $doc = $this->documento();

        app(EmissorFiscal::class)->comunicar($doc, $this->tenant->id);

        $this->assertSame(0, AGTSubmission::where('document_id', $doc->id)->count());
    }
}

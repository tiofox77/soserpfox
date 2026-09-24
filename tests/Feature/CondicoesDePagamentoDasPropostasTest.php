<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesQuote;
use Tests\TenantTestCase;

/**
 * AS CONDIÇÕES DE PAGAMENTO NAS PROFORMAS E NOS ORÇAMENTOS (24/09/2026).
 *
 * O campo «Condições» das definições existia e não era usado em lado nenhum,
 * e a proforma nem imprimia as condições. Agora: nascem em cada documento
 * novo, mudam-se no documento, e saem no rodapé — as do documento, ou as da
 * empresa quando o documento não tem.
 */
class CondicoesDePagamentoDasPropostasTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/emissor';

    private const DA_EMPRESA = "A) 70% com a adjudicação, 30% após a conclusão.\nB) Caduca ao fim de 15 dias.";

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
        InvoicingSettings::forTenant($this->tenant->id)->update(['default_terms' => self::DA_EMPRESA]);
        InvoicingSettings::esquecerMemoria();
    }

    public function test_um_documento_novo_nasce_com_as_condicoes_da_empresa_e_as_compras_nao(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.sales.proformas.view', 'invoicing.purchases.proformas.view');

        $this->getJson(self::RAIZ . '/orcamentos/opcoes')->assertOk()->assertJsonPath('condicoes_padrao', self::DA_EMPRESA);
        $this->getJson(self::RAIZ . '/proformas-venda/opcoes')->assertOk()->assertJsonPath('condicoes_padrao', self::DA_EMPRESA);
        $this->getJson(self::RAIZ . '/proformas-compra/opcoes')->assertOk()->assertJsonPath('condicoes_padrao', null);
    }

    public function test_as_condicoes_de_um_documento_passam_das_2000_letras(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.create');
        $artigo = \App\Models\Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Edição de vídeo', 'sku' => 'S-' . uniqid(),
            'price' => 1000, 'cost' => 0, 'type' => 'servico', 'manage_stock' => false,
        ]);

        $id = $this->postJson(self::RAIZ . '/orcamentos', [
            'parte_id' => $this->cliente->id,
            'data' => now()->toDateString(),
            'condicoes' => str_repeat('Condição longa. ', 200),
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $this->assertGreaterThan(2000, mb_strlen((string) SalesQuote::find($id)->terms));
    }

    public function test_a_proforma_sem_condicoes_imprime_as_da_empresa_no_rodape(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');
        $p = $this->proforma(null);

        $this->get("/invoicing/sales/proformas/{$p->id}/preview")
            ->assertOk()
            ->assertSee('Condições e políticas de pagamento')
            ->assertSee('A) 70% com a adjudicação, 30% após a conclusão.<br />', false);
    }

    public function test_as_condicoes_do_documento_mandam_sobre_as_da_empresa(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');
        $p = $this->proforma('Pagamento a pronto, só para este cliente.');

        $this->get("/invoicing/sales/proformas/{$p->id}/preview")
            ->assertOk()
            ->assertSee('Pagamento a pronto, só para este cliente.')
            ->assertDontSee('70% com a adjudicação');
    }

    public function test_o_orcamento_imprime_as_condicoes_uma_vez_no_rodape(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');
        $q = SalesQuote::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id, 'created_by' => $this->user->id,
            'quote_date' => now()->toDateString(), 'status' => 'draft', 'terms' => 'Validade de 15 dias.',
        ]);

        $html = $this->get("/invoicing/sales/quotes/{$q->id}/preview")->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Validade de 15 dias.'), 'as condições saem uma vez, no rodapé');
        $this->assertStringContainsString('Condições e políticas de pagamento', $html);
        $this->assertStringNotContainsString('Termos e Condições', $html);
    }

    private function proforma(?string $condicoes): SalesProforma
    {
        return SalesProforma::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'proforma_number' => 'PRF/' . random_int(1000, 9999),
            'proforma_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'terms' => $condicoes,
            'created_by' => $this->user->id,
        ]);
    }
}

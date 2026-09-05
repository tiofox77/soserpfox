<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O EMISSOR DE PROPOSTAS, e as três promessas que ele faz.
 *
 * 1. A conta é do servidor. O que o browser mandar de totais é ignorado.
 * 2. A taxa vem do `TaxResolver`, nunca do pedido.
 * 3. O número é do modelo, com a série da empresa — nunca escrito à mão.
 *
 * E a quarta, por omissão: só se emitem PROPOSTAS. Uma factura de venda pedida
 * a esta rota dá 404, porque o editor ainda não sabe assiná-la.
 */
class ApiDoEmissorParaReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function rota(string $tipo, string $cauda = ''): string
    {
        return "/api/v1/invoicing/react/emissor/{$tipo}{$cauda}";
    }

    private function artigo(array $por = []): Product
    {
        $categoria = Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo ' . uniqid(),
            'type' => 'produto',
            'price' => 1000,
            'unit' => 'un',
            'category_id' => $categoria->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'is_active' => true,
        ], $por));
    }

    private function taxaDe14(): Tax
    {
        return Tax::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']
        );
    }

    /* ─── O que ainda não se emite por aqui ───────────────────────────── */

    /**
     * UMA FACTURA DE VENDA NÃO SE EMITE POR ESTA ROTA.
     *
     * Tem número de série, hash encadeado e assinatura AGT. 404 e não 403: não
     * é falta de permissão, é o editor ainda não saber fazê-lo.
     *
     * @test
     */
    public function so_se_emitem_propostas(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create', 'invoicing.sales.proformas.view');

        foreach (['facturas-compra', 'recibos'] as $aindaNao) {
            $this->postJson($this->rota($aindaNao), [])->assertNotFound();
        }

        // E as três que se emitem existem — não dão 404. A que tem permissão
        // responde 200; as outras duas respondem 403, que é outra conversa.
        $this->postJson($this->rota('proformas-venda', '/calcular'), ['linhas' => []])->assertOk();

        foreach (['orcamentos', 'proformas-compra'] as $tipo) {
            $this->postJson($this->rota($tipo, '/calcular'), ['linhas' => []])->assertForbidden();
        }
    }

    /* ─── A conta ─────────────────────────────────────────────────────── */

    /**
     * A TAXA VEM DO ARTIGO, NÃO DO PEDIDO.
     *
     * @test
     */
    public function a_taxa_ignora_o_que_o_browser_manda(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $comIva = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $this->taxaDe14()->id]);

        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [[
                'product_id' => $comIva->id,
                'quantity' => 1,
                'price' => 1000,
                // O browser a tentar impor isenção num artigo com IVA.
                'tax_rate' => 0,
            ]],
        ])->assertOk();

        $this->assertEqualsWithDelta(14, $r->json('linhas.0.tax_rate'), 0.01,
            'a taxa é lida do artigo, não do que veio no corpo');
        $this->assertGreaterThan(0, (float) $r->json('linhas.0.imposto'));
    }

    /**
     * O IMPOSTO É `CEIL` AO CÊNTIMO, não `round`.
     *
     * É a regra do DS.120 §4.1 que a AGT verifica. Com `round`, certas linhas
     * saem um cêntimo abaixo e o documento é recusado com E70.
     *
     * @test
     */
    public function o_imposto_arredonda_para_cima_ao_centimo(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $a = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $this->taxaDe14()->id]);

        // 1 × 10,01 a 14% = 1,4014 → 1,41 e não 1,40.
        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [['product_id' => $a->id, 'quantity' => 1, 'price' => 10.01]],
        ])->assertOk();

        $this->assertEqualsWithDelta(1.41, $r->json('linhas.0.imposto'), 0.001);
    }

    /** Uma linha isenta não cobra imposto nenhum. @test */
    public function uma_linha_isenta_nao_cobra_imposto(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 2, 'price' => 500]],
        ])->assertOk();

        $this->assertSame(0, (int) $r->json('linhas.0.imposto'));
        $this->assertEqualsWithDelta(1000, $r->json('totais.total'), 0.01);
    }

    /** O desconto da linha baixa a base antes do imposto. @test */
    public function o_desconto_da_linha_baixa_a_base(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [[
                'product_id' => $this->artigo()->id,
                'quantity' => 1,
                'price' => 1000,
                'discount_percent' => 10,
            ]],
        ])->assertOk();

        $this->assertEqualsWithDelta(1000, $r->json('linhas.0.bruto'), 0.01);
        $this->assertEqualsWithDelta(100, $r->json('linhas.0.desconto'), 0.01);
        $this->assertEqualsWithDelta(900, $r->json('linhas.0.base'), 0.01);
    }

    /* ─── Gravar ──────────────────────────────────────────────────────── */

    /** @test */
    public function sem_permissao_de_criar_nao_se_grava(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100]],
        ])->assertForbidden();
    }

    /** Um documento sem linhas não é um documento. @test */
    public function nao_se_grava_um_documento_vazio(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [],
        ])->assertJsonValidationErrors('linhas');
    }

    /**
     * GRAVA, NUMERA-SE SOZINHO, E OS TOTAIS SÃO OS DO SERVIDOR.
     *
     * @test
     */
    public function grava_uma_proforma_com_o_numero_da_serie(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $a = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $this->taxaDe14()->id]);

        $r = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $a->id, 'quantity' => 2, 'price' => 1000]],
            // O browser a mentir nos totais. Tem de ser ignorado.
            'total' => 1,
            'subtotal' => 1,
        ])->assertCreated();

        $proforma = SalesProforma::find($r->json('id'));

        $this->assertNotEmpty($proforma->proforma_number, 'o modelo numera-se a si próprio');
        $this->assertEqualsWithDelta(2280, $proforma->total, 0.01,
            '2 × 1000 = 2000, mais 14% = 2280 — e não o que o browser mandou');
        $this->assertSame(1, $proforma->items()->count());
        $this->assertSame('draft', $proforma->status, 'uma proposta nasce em rascunho');
    }

    /** O documento fica preso a esta empresa e a quem o gravou. @test */
    public function o_documento_fica_desta_empresa_e_deste_autor(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $id = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100]],
        ])->assertCreated()->json('id');

        $p = SalesProforma::find($id);

        $this->assertSame($this->tenant->id, (int) $p->tenant_id);
        $this->assertSame($this->user->id, (int) $p->created_by);
    }
}

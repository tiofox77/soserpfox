<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Invoicing\PurchaseProformaItem;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProformaItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * A região fiscal do documento existe onde há imposto a calcular.
 *
 * Cabinda tem regime de IVA próprio (AO-CAB), e o que o determina é o LOCAL DA
 * OPERAÇÃO — a mesma entidade pode comprar em Luanda e em Cabinda. O selector
 * existia só nas facturas de venda: as proformas calculavam sempre à taxa
 * continental, e uma proposta feita em Cabinda saía com o imposto errado.
 *
 * Isso não é diferença de aspecto entre ecrãs, é imposto errado.
 *
 * OS ECRÃS SÃO AGORA EM REACT e a página só traz a casca: um `assertSee`
 * ao HTML deixou de provar seja o que for. A região prova-se onde ela conta —
 * nas OPÇÕES que o ecrã recebe (é de lá que sai o selector) e nas LINHAS
 * GRAVADAS (é lá que ela vale). As duas regras de sempre mantêm-se: vazio
 * significa automática, e deriva da província da outra parte; e a região
 * escolhida chega mesmo às linhas — nas proformas e nas compras também, que
 * foi onde o selector esteve, por um commit, a não fazer nada.
 */
class RegiaoFiscalDocumentosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(): Product
    {
        return Product::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Artigo ' . uniqid(),
            'sku'          => 'SKU' . strtoupper(substr(uniqid(), -8)),
            'type'         => 'produto',
            'price'        => 1000,
            'cost'         => 500,
            'unit'         => 'UN',
            'tax_type'     => 'iva',
            'tax_rate_id'  => $this->imposto->id,
            'manage_stock' => false,
            'is_active'    => true,
        ]);
    }

    private function clienteDe(string $provincia): Client
    {
        return Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente de ' . $provincia,
            'nif'       => (string) random_int(200000000, 299999999),
            'type'      => 'pessoa_juridica',
            'province'  => $provincia,
            'is_active' => true,
        ]);
    }

    private function fornecedor(): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Fornecedor ' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);
    }

    /* ─── O ecrã tem por onde escolher ────────────────────────────────── */

    /** @test */
    public function a_pagina_da_factura_de_venda_continua_a_abrir(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        // A página traz só a casca do React: prova-se que ela existe e que
        // pede a permissão. O conteúdo prova-se pela API, a seguir.
        $this->actingAs($this->user)->get('/invoicing/sales/invoices/create')->assertOk();
    }

    /** @test */
    public function as_opcoes_da_factura_de_venda_trazem_o_selector(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $regioes = collect($this->getJson(self::RAIZ . '/factura/opcoes')->assertOk()->json('regioes'));

        $this->assertTrue($regioes->contains('valor', 'AO-CAB'), 'Cabinda tem de estar na lista');
        $this->assertTrue($regioes->contains('valor', ''), 'e a automática também');
    }

    /**
     * O SELECTOR CHEGOU ÀS PROPOSTAS.
     *
     * Era este o ecrã que faltava: sem opção nenhuma, uma proposta feita em
     * Cabinda saía à taxa continental e a factura que dela nascesse herdava-o.
     *
     * @test
     */
    public function as_opcoes_das_propostas_passam_a_ter_o_selector(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view', 'invoicing.sales.quotes.view', 'invoicing.purchases.proformas.view');

        foreach (['proformas-venda', 'orcamentos', 'proformas-compra'] as $tipo) {
            $regioes = collect($this->getJson(self::RAIZ . "/emissor/{$tipo}/opcoes")->assertOk()->json('regioes'));

            $this->assertTrue($regioes->contains('valor', 'AO-CAB'), "{$tipo} sem Cabinda para escolher");
            $this->assertTrue($regioes->contains('valor', ''), "{$tipo} sem a região automática");
        }
    }

    /** @test */
    public function as_opcoes_da_compra_tambem_trazem_o_selector(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $regioes = collect($this->getJson(self::RAIZ . '/compra/opcoes')->assertOk()->json('regioes'));

        $this->assertTrue($regioes->contains('valor', 'AO-CAB'));
    }

    /* ─── Vazio significa automática ──────────────────────────────────── */

    /**
     * VAZIO É "AUTOMÁTICA": deriva da província do cliente. Fixar AO por
     * omissão era o que fazia Cabinda passar despercebida.
     *
     * @test
     */
    public function por_omissao_a_regiao_deriva_da_provincia(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create', 'invoicing.sales.proformas.create');

        $deCabinda = $this->clienteDe('Cabinda');
        $deLuanda = $this->clienteDe('Luanda');
        $artigo = $this->artigo();

        // Factura de venda, sem região nenhuma no pedido.
        $id = $this->postJson(self::RAIZ . '/factura', [
            'client_id' => $deCabinda->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(), 'status' => 'draft',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $this->assertSame('AO-CAB', SalesInvoice::findOrFail($id)->items()->first()->tax_country_region);

        // Proforma de venda, o mesmo — e o continente continua a ser AO.
        $proforma = $this->postJson(self::RAIZ . '/emissor/proformas-venda', [
            'parte_id' => $deLuanda->id, 'data' => now()->toDateString(),
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $this->assertSame('AO', SalesProformaItem::where('sales_proforma_id', $proforma)->first()->tax_country_region);
    }

    /* ─── A região escolhida chega às linhas ──────────────────────────── */

    /**
     * O selector sozinho não chega: a proforma gravava a região FIXA nas
     * linhas, e escolher Cabinda no ecrã não mudava nada.
     *
     * @test
     */
    public function a_regiao_escolhida_chega_as_linhas_da_proposta(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.quotes.create');

        $artigo = $this->artigo();
        $doContinente = $this->clienteDe('Luanda');

        $proforma = $this->postJson(self::RAIZ . '/emissor/proformas-venda', [
            'parte_id' => $doContinente->id, 'data' => now()->toDateString(),
            'tax_country_region' => 'AO-CAB',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $this->assertSame('AO-CAB',
            SalesProformaItem::where('sales_proforma_id', $proforma)->first()->tax_country_region,
            'a região das linhas não pode voltar a ser um valor fixo');

        // E o editor devolve-a ao abrir: quem reabre o rascunho vê a que
        // escolheu, e não a do continente.
        $this->comPermissoes('invoicing.sales.proformas.view');
        $this->assertSame('AO-CAB',
            $this->getJson(self::RAIZ . '/emissor/proformas-venda/' . $proforma)->assertOk()
                ->json('documento.tax_country_region'));
    }

    /** @test */
    public function a_regiao_escolhida_chega_as_linhas_da_compra(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create', 'invoicing.purchases.proformas.create');

        $artigo = $this->artigo();

        $compra = $this->postJson(self::RAIZ . '/compra', [
            'supplier_id' => $this->fornecedor()->id, 'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(), 'status' => 'draft',
            'tax_country_region' => 'AO-CAB',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 2, 'price' => 500]],
        ])->assertCreated()->json('id');

        $this->assertSame('AO-CAB',
            PurchaseInvoiceItem::where('purchase_invoice_id', $compra)->first()->tax_country_region);

        $proforma = $this->postJson(self::RAIZ . '/emissor/proformas-compra', [
            'parte_id' => $this->fornecedor()->id, 'data' => now()->toDateString(),
            'tax_country_region' => 'AO-CAB',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 500]],
        ])->assertCreated()->json('id');

        $this->assertSame('AO-CAB',
            PurchaseProformaItem::where('purchase_proforma_id', $proforma)->first()->tax_country_region);
    }

    /** Uma região inventada não passa em silêncio. @test */
    public function uma_regiao_que_nao_existe_e_recusada(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $this->postJson(self::RAIZ . '/emissor/proformas-venda', [
            'parte_id' => $this->cliente->id, 'data' => now()->toDateString(),
            'tax_country_region' => 'PT',
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100]],
        ])->assertStatus(422)->assertJsonValidationErrors('tax_country_region');
    }

    /* ─── E o sítio onde ela se grava ─────────────────────────────────── */

    public function test_as_linhas_de_compra_tem_onde_guardar_a_regiao(): void
    {
        // As linhas de compra nem coluna tinham para a regiao: sem migracao,
        // levar o selector a estes ecras seria decorativo.
        foreach ([
            'invoicing_purchase_invoice_items',
            'invoicing_purchase_proforma_items',
            'invoicing_sales_proforma_items',
            'invoicing_sales_quote_items',
        ] as $tabela) {
            $this->assertTrue(
                Schema::hasColumn($tabela, 'tax_country_region'),
                $tabela . ' tem de ter a coluna, senao o selector nao grava nada'
            );
        }
    }
}

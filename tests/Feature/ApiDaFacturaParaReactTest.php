<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * A API DA FACTURA DE VENDA, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: a taxa vem do `TaxResolver`
 * mesmo que o pedido minta; a FR nasce paga e exige forma de pagamento; o
 * armazém só é obrigatório com artigos físicos; a factura sai com número da
 * série, hash e os campos SAFT a fechar (net + tax = gross).
 */
class ApiDaFacturaParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/factura';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(float $preco, bool $comIva, string $tipo = 'produto'): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = $comIva ? Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']) : null;

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => $tipo, 'price' => $preco,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => $comIva ? 'iva' : 'isento',
            'tax_rate_id' => $taxa?->id, 'exemption_reason' => $comIva ? null : 'M99',
            'manage_stock' => $tipo === 'produto', 'stock_quantity' => 100, 'is_active' => true,
        ]);
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'client_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo(1000, true)->id, 'quantity' => 2, 'price' => 1000]],
        ], $por);
    }

    /** @test */
    public function sem_permissao_de_criar_nao_ha_nada(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->postJson(self::RAIZ . '/calcular', ['linhas' => []])->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /** A taxa é a do artigo, não a do pedido. @test */
    public function a_taxa_vem_do_artigo_mesmo_que_o_pedido_minta(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $comIva = $this->artigo(1000, true);

        $r = $this->postJson(self::RAIZ . '/calcular', [
            'linhas' => [['product_id' => $comIva->id, 'quantity' => 1, 'price' => 1000, 'tax_rate' => 0]],
        ])->assertOk();

        $this->assertEqualsWithDelta(14, $r->json('linhas.0.tax_rate'), 0.01);
    }

    /**
     * EMITE UMA FT: número da série, hash, e os campos SAFT a fechar.
     *
     * @test
     */
    public function emite_uma_factura_com_numero_hash_e_saft_a_fechar(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $r = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();

        $f = SalesInvoice::find($r->json('id'));

        $this->assertNotEmpty($f->invoice_number, 'numera-se pela série');
        $this->assertNotEmpty($f->saft_hash, 'entra na cadeia');
        $this->assertSame('F', $f->invoice_status, 'emitida, não rascunho');
        $this->assertEqualsWithDelta(2000, $f->net_total, 0.01);
        $this->assertEqualsWithDelta(280, $f->tax_payable, 0.01);
        $this->assertEqualsWithDelta($f->net_total + $f->tax_payable, $f->gross_total, 0.01, 'net + tax = gross, senão a AGT recusa');
        $this->assertSame(1, $f->items()->count());
        $this->assertSame('NOR', $f->items()->first()->tax_code);
    }

    /** A FR exige forma de pagamento e nasce paga. @test */
    public function a_factura_recibo_exige_pagamento_e_nasce_paga(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->postJson(self::RAIZ, $this->corpo(['invoice_type' => 'FR']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $r = $this->postJson(self::RAIZ, $this->corpo(['invoice_type' => 'FR', 'payment_method' => 'cash']))
            ->assertCreated();

        $f = SalesInvoice::find($r->json('id'));

        $this->assertSame('FR', $f->invoice_type);
        $this->assertSame('paid', $f->status, 'a FR vale como recibo: fica liquidada');
        $this->assertEqualsWithDelta($f->total, $f->paid_amount, 0.01);
    }

    /** O armazém só é obrigatório com artigos físicos. @test */
    public function o_armazem_so_e_obrigatorio_com_artigos_fisicos(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->postJson(self::RAIZ, $this->corpo(['warehouse_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');

        $servico = $this->artigo(500, false, 'servico');

        $this->postJson(self::RAIZ, $this->corpo([
            'warehouse_id' => null,
            'linhas' => [['product_id' => $servico->id, 'quantity' => 1, 'price' => 500]],
        ]))->assertCreated();
    }

    /** O rascunho não assina nem baixa stock. @test */
    public function um_rascunho_nao_e_finalizado(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $r = $this->postJson(self::RAIZ, $this->corpo(['status' => 'draft']))->assertCreated();

        $f = SalesInvoice::find($r->json('id'));

        $this->assertSame('draft', $f->status);
        $this->assertSame('N', $f->invoice_status);
    }

    /** Sem linhas não há factura. @test */
    public function sem_linhas_nao_ha_factura(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->postJson(self::RAIZ, $this->corpo(['linhas' => []]))->assertJsonValidationErrors('linhas');
    }
}

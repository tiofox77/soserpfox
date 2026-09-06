<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Supplier;
use Tests\TenantTestCase;

/**
 * ABRIR E EDITAR DOCUMENTOS EXISTENTES, para os ecrãs em React.
 *
 * O que estes ensaios guardam: um rascunho de factura, de proposta ou de
 * compra abre-se no editor e altera-se; depois de emitido abre-se só para
 * ler e o servidor recusa mexer; recibos e notas abrem-se para consulta.
 */
class ApiDeAbrirEEditarParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        // Sem série não se emite recibo nem nota — o TenantTestCase só semeia as de factura e de POS.
        foreach (['RC' => 'receipt', 'NC' => 'credit_note', 'ND' => 'debit_note'] as $codigo => $tipo) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => $codigo, 'name' => $codigo . ' (ensaio)',
                'document_type' => $tipo, 'agt_environment' => 'sandbox', 'is_default' => true, 'is_active' => true,
            ]);
        }
    }

    private function artigo(): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000, 'cost' => 600,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => 'iva', 'tax_rate_id' => $taxa->id,
            'manage_stock' => true, 'stock_quantity' => 100, 'is_active' => true,
        ]);
    }

    private function fornecedor(): Supplier
    {
        return Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor ' . uniqid(), 'nif' => '5000000000', 'type' => 'pessoa_juridica', 'is_active' => true]);
    }

    /** @test */
    public function um_rascunho_de_factura_abre_se_edita_se_e_depois_de_emitida_so_se_le(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view', 'invoicing.sales.invoices.create');
        $artigo = $this->artigo();
        $corpo = fn (array $por = []) => array_merge([
            'client_id' => $this->clienteEmpresa()->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(), 'status' => 'draft',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 2, 'price' => 1000]],
        ], $por);

        $id = $this->postJson(self::RAIZ . '/factura', $corpo())->assertCreated()->json('id');

        $aberta = $this->getJson(self::RAIZ . '/factura/' . $id)->assertOk();
        $this->assertTrue($aberta->json('documento.pode_editar'));
        $this->assertSame('draft', $aberta->json('documento.estado'));
        $this->assertCount(1, $aberta->json('linhas'));
        $this->assertEqualsWithDelta(2, $aberta->json('linhas.0.quantity'), 0.001);

        $this->putJson(self::RAIZ . '/factura/' . $id, $corpo(['linhas' => [['product_id' => $artigo->id, 'quantity' => 3, 'price' => 1000]]]))->assertOk();
        $this->assertEqualsWithDelta(3, $this->getJson(self::RAIZ . '/factura/' . $id)->json('linhas.0.quantity'), 0.001);

        // Emitir a partir do rascunho: fica com número e hash, e fecha-se.
        $emitida = $this->putJson(self::RAIZ . '/factura/' . $id, $corpo(['status' => 'pending']))->assertOk();
        $this->assertNotEmpty($emitida->json('numero'));

        $depois = $this->getJson(self::RAIZ . '/factura/' . $id)->assertOk();
        $this->assertFalse($depois->json('documento.pode_editar'), 'emitida, só se lê');
        $this->putJson(self::RAIZ . '/factura/' . $id, $corpo(['linhas' => [['product_id' => $artigo->id, 'quantity' => 4, 'price' => 1000]]]))->assertStatus(422);

        $this->getJson(self::RAIZ . '/factura/999999')->assertNotFound();
    }

    /** @test */
    public function um_rascunho_de_proposta_abre_se_e_edita_se(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view', 'invoicing.sales.proformas.create', 'invoicing.sales.proformas.edit');
        $artigo = $this->artigo();
        $cliente = $this->clienteEmpresa();

        $criada = $this->postJson(self::RAIZ . '/emissor/proformas-venda', [
            'parte_id' => $cliente->id, 'data' => now()->toDateString(), 'linhas' => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated();
        $id = $criada->json('id');

        $aberta = $this->getJson(self::RAIZ . '/emissor/proformas-venda/' . $id)->assertOk();
        $this->assertTrue($aberta->json('documento.pode_editar'));
        $this->assertSame($cliente->id, $aberta->json('documento.parte_id'));
        $this->assertCount(1, $aberta->json('linhas'));

        $this->putJson(self::RAIZ . '/emissor/proformas-venda/' . $id, [
            'parte_id' => $cliente->id, 'data' => now()->toDateString(), 'notas' => 'Nova nota',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 2, 'price' => 1000], ['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 50]],
        ])->assertOk();

        $depois = $this->getJson(self::RAIZ . '/emissor/proformas-venda/' . $id)->assertOk();
        $this->assertSame('Nova nota', $depois->json('documento.notas'));
        $this->assertCount(2, $depois->json('linhas'));
        $this->assertSame($criada->json('numero'), $depois->json('documento.numero'), 'o número fica');

        $this->getJson(self::RAIZ . '/emissor/proformas-venda/999999')->assertNotFound();
    }

    /** @test */
    public function uma_compra_em_rascunho_edita_se_e_registada_so_se_le(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create');
        $artigo = $this->artigo();
        $corpo = fn (array $por = []) => array_merge([
            'supplier_id' => $this->fornecedor()->id, 'warehouse_id' => $this->armazem->id, 'invoice_date' => now()->toDateString(), 'status' => 'draft',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 2, 'price' => 600]],
        ], $por);

        $id = $this->postJson(self::RAIZ . '/compra', $corpo())->assertCreated()->json('id');

        $this->assertTrue($this->getJson(self::RAIZ . '/compra/' . $id)->assertOk()->json('documento.pode_editar'));

        $this->putJson(self::RAIZ . '/compra/' . $id, $corpo(['linhas' => [['product_id' => $artigo->id, 'quantity' => 5, 'price' => 600]]]))->assertOk();
        $this->assertEqualsWithDelta(5, $this->getJson(self::RAIZ . '/compra/' . $id)->json('linhas.0.quantity'), 0.001);

        $this->putJson(self::RAIZ . '/compra/' . $id, $corpo(['status' => 'pending']))->assertOk();
        $this->assertFalse($this->getJson(self::RAIZ . '/compra/' . $id)->assertOk()->json('documento.pode_editar'), 'registada, o stock já entrou');
        $this->putJson(self::RAIZ . '/compra/' . $id, $corpo())->assertStatus(422);
    }

    /** @test */
    public function um_recibo_e_uma_nota_abrem_se_para_consulta(): void
    {
        $this->comPermissoes(
            'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create',
            'invoicing.receipts.view', 'invoicing.receipts.create',
            'invoicing.credit-notes.view', 'invoicing.credit-notes.create', 'invoicing.debit-notes.view',
        );
        $artigo = $this->artigo();
        $cliente = $this->clienteEmpresa();

        $factura = $this->postJson(self::RAIZ . '/factura', [
            'client_id' => $cliente->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT', 'invoice_date' => now()->toDateString(), 'status' => 'pending',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 2, 'price' => 1000]],
        ])->assertCreated();

        $recibo = $this->postJson(self::RAIZ . '/recibos', [
            'type' => 'sale', 'client_id' => $cliente->id, 'invoice_id' => $factura->json('id'), 'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'amount_paid' => 500,
        ])->assertCreated();

        $aberto = $this->getJson(self::RAIZ . '/recibos/' . $recibo->json('id'))->assertOk();
        $this->assertSame($recibo->json('numero'), $aberto->json('recibo.numero'));
        $this->assertEqualsWithDelta(500, $aberto->json('recibo.amount_paid'), 0.001);
        $this->assertSame($factura->json('numero'), $aberto->json('recibo.factura'));

        $linhas = $this->getJson(self::RAIZ . '/notas/credito/facturas/' . $factura->json('id') . '/linhas')->assertOk()->json('data');
        $nota = $this->postJson(self::RAIZ . '/notas/credito', [
            'client_id' => $cliente->id, 'invoice_id' => $factura->json('id'), 'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'partial',
            'linhas' => [['origem_line_id' => $linhas[0]['origem_line_id'], 'quantity' => 1]],
        ])->assertCreated();

        $abertaNota = $this->getJson(self::RAIZ . '/notas/credito/' . $nota->json('id'))->assertOk();
        $this->assertSame($nota->json('numero'), $abertaNota->json('nota.numero'));
        $this->assertSame($factura->json('numero'), $abertaNota->json('nota.factura'));
        $this->assertCount(1, $abertaNota->json('nota.linhas'));

        $this->getJson(self::RAIZ . '/notas/debito/' . $nota->json('id'))->assertNotFound();
    }
}

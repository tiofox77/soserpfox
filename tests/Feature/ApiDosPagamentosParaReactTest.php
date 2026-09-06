<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\Advance;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Treasury\Transaction;
use Tests\TenantTestCase;

/**
 * A API DE PAGAR UMA FACTURA, para as listas em React.
 *
 * O que ela promete e estes ensaios guardam: pagar emite um recibo e lança
 * o movimento de tesouraria, e a factura fica paga pelo recibo (não somada
 * duas vezes); o excedente de uma venda vira adiantamento; um adiantamento
 * usado abate a factura; a compra vai na sua coluna; e a permissão é a de
 * criar recibos.
 */
class ApiDosPagamentosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/pagamentos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        // Sem série de RECIBOS não se emite recibo nenhum — e o TenantTestCase
        // só semeia as de factura e de POS.
        \App\Models\Invoicing\InvoicingSeries::create([
            'tenant_id' => $this->tenant->id,
            'series_code' => 'RC',
            'name' => 'RC (ensaio)',
            'document_type' => 'receipt',
            'agt_environment' => 'sandbox',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function artigo(): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000, 'cost' => 0,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => 'iva', 'tax_rate_id' => $taxa->id,
            'manage_stock' => true, 'stock_quantity' => 100, 'is_active' => true,
        ]);
    }

    /** Uma FT de 1.140 Kz (1.000 + 14%), emitida pela API da factura. */
    private function factura(): SalesInvoice
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $id = $this->postJson('/api/v1/invoicing/react/factura', [
            'client_id' => $this->clienteEmpresa()->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        return SalesInvoice::find($id);
    }

    /** @test */
    public function a_permissao_e_a_de_criar_recibos(): void
    {
        $f = $this->factura();

        $this->getJson(self::RAIZ . '/sale/' . $f->id)->assertForbidden();
        $this->postJson(self::RAIZ . '/sale/' . $f->id, ['amount' => 10, 'payment_method' => 'cash'])->assertForbidden();
    }

    /** @test */
    public function pagar_emite_um_recibo_e_a_factura_fica_paga_uma_so_vez(): void
    {
        $f = $this->factura();
        $this->comPermissoes('invoicing.receipts.create');

        $ctx = $this->getJson(self::RAIZ . '/sale/' . $f->id)->assertOk();
        $this->assertEqualsWithDelta(1140, $ctx->json('por_pagar'), 0.01);

        $r = $this->postJson(self::RAIZ . '/sale/' . $f->id, ['amount' => 1140, 'payment_method' => 'transfer', 'reference' => 'TRF 1'])->assertCreated();

        $f = $f->fresh();

        $this->assertNotNull($r->json('recibo'));
        $this->assertSame('paid', $f->status);
        $this->assertEqualsWithDelta(1140, $f->paid_amount, 0.01, 'o recibo lança o valor uma vez, não duas');
        $this->assertSame(1, Receipt::where('invoice_id', $f->id)->count());
        $this->assertSame(1, Transaction::where('tenant_id', $this->tenant->id)->where('invoice_id', $f->id)->where('type', 'income')->count(), 'entrou na tesouraria');
    }

    /** @test */
    public function o_excedente_de_uma_venda_vira_adiantamento(): void
    {
        $f = $this->factura();
        $this->comPermissoes('invoicing.receipts.create');

        $r = $this->postJson(self::RAIZ . '/sale/' . $f->id, ['amount' => 1500, 'payment_method' => 'cash'])->assertCreated();

        $this->assertEqualsWithDelta(360, $r->json('excedente'), 0.01);

        $a = Advance::where('tenant_id', $this->tenant->id)->where('client_id', $f->client_id)->first();
        $this->assertNotNull($a);
        $this->assertEqualsWithDelta(360, $a->remaining_amount, 0.01);
    }

    /** @test */
    public function um_adiantamento_abate_a_factura(): void
    {
        $f = $this->factura();
        $this->comPermissoes('invoicing.receipts.create');

        $a = Advance::create([
            'tenant_id' => $this->tenant->id, 'type' => 'sale', 'client_id' => $f->client_id, 'payment_date' => now(),
            'amount' => 500, 'payment_method' => 'cash', 'status' => 'available', 'created_by' => $this->user->id,
        ]);

        $ctx = $this->getJson(self::RAIZ . '/sale/' . $f->id)->assertOk();
        $this->assertSame($a->id, $ctx->json('adiantamentos.0.id'));

        $this->postJson(self::RAIZ . '/sale/' . $f->id, ['amount' => 640, 'payment_method' => 'cash', 'advance_id' => $a->id, 'advance_amount' => 500])
            ->assertCreated();

        $this->assertEqualsWithDelta(0, $a->fresh()->remaining_amount, 0.01, 'o adiantamento ficou gasto');
        $this->assertSame('paid', $f->fresh()->status);
        $this->assertEqualsWithDelta(1140, $f->fresh()->paid_amount, 0.01, '640 do recibo + 500 do adiantamento');
    }

    /** @test */
    public function sem_valor_nenhum_o_servidor_recusa(): void
    {
        $f = $this->factura();
        $this->comPermissoes('invoicing.receipts.create');

        $this->postJson(self::RAIZ . '/sale/' . $f->id, ['amount' => 0, 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson(self::RAIZ . '/sale/' . $f->id, ['amount' => 10, 'payment_method' => 'bitcoin'])
            ->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    /** A compra vai na sua coluna, e o recibo dela não vai à AGT. @test */
    public function a_compra_paga_se_pela_sua_coluna(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create', 'invoicing.receipts.create');

        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor', 'type' => 'pessoa_juridica', 'is_active' => true]);

        $id = $this->postJson('/api/v1/invoicing/react/compra', [
            'supplier_id' => $fornecedor->id, 'warehouse_id' => $this->armazem->id, 'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 2, 'price' => 500]],
        ])->assertCreated()->json('id');

        $ctx = $this->getJson(self::RAIZ . '/purchase/' . $id)->assertOk();
        $this->assertSame([], $ctx->json('adiantamentos'), 'compras não usam adiantamentos');

        $this->postJson(self::RAIZ . '/purchase/' . $id, ['amount' => $ctx->json('por_pagar'), 'payment_method' => 'cash'])->assertCreated();

        $recibo = Receipt::where('purchase_invoice_id', $id)->first();
        $this->assertNotNull($recibo);
        $this->assertNull($recibo->invoice_id, 'nunca na coluna das vendas');
        $this->assertSame('paid', PurchaseInvoice::find($id)->status);
    }
}

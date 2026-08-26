<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\ActualizarCustoDeCompra;
use Tests\TenantTestCase;

/**
 * O preço a que se comprou passa a ser o custo do artigo.
 *
 * Antes, lançar uma compra com preço novo deixava o catálogo com o custo
 * antigo — e a margem passava a ser calculada sobre um valor de há meses.
 */
class CustoDoArtigoSegueACompraTest extends TenantTestCase
{
    private Supplier $fornecedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fornecedor = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Distribuidora Teste',
            'is_active' => true,
        ]);
    }

    private function artigo(float $custo): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'ART-' . uniqid(),
            'name'      => 'Artigo de Teste',
            'price'     => 10000,
            'cost'      => $custo,
            'is_active' => true,
        ]);
    }

    /**
     * Cria a compra como o formulário faz: rascunho → linhas → estado final.
     * É a transição draft→recebida que dá entrada do stock e fixa o custo.
     */
    private function comprar(array $linhas, string $estado = 'pending'): PurchaseInvoice
    {
        $factura = PurchaseInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $this->fornecedor->id,
            'warehouse_id'   => $this->armazem->id,
            'invoice_number' => 'FC-' . uniqid(),
            'invoice_date'   => now(),
            'status'         => 'draft',
            'created_by'     => $this->user->id,
            'subtotal'       => 0,
            'total'          => 0,
        ]);

        foreach ($linhas as $linha) {
            PurchaseInvoiceItem::create(array_merge([
                'purchase_invoice_id' => $factura->id,
                'quantity'            => 1,
                'discount_amount'     => 0,
                'subtotal'            => 0,
                'total'               => 0,
            ], $linha));
        }

        $factura->status = $estado;
        $factura->save();

        return $factura->refresh();
    }

    public function test_preco_de_compra_novo_vira_custo_do_artigo(): void
    {
        $artigo = $this->artigo(500);

        $this->comprar([[
            'product_id'   => $artigo->id,
            'product_name' => $artigo->name,
            'quantity'     => 10,
            'unit_price'   => 750,
        ]]);

        $this->assertEquals(750.00, (float) $artigo->refresh()->cost);
    }

    /** O desconto do fornecedor faz parte do preço: 1000 com 10% custa 900. */
    public function test_desconto_da_linha_entra_no_custo(): void
    {
        $artigo = $this->artigo(500);

        $this->comprar([[
            'product_id'       => $artigo->id,
            'product_name'     => $artigo->name,
            'quantity'         => 4,
            'unit_price'       => 1000,
            'discount_percent' => 10,
            'discount_amount'  => 400,   // 10% de 4 x 1000
        ]]);

        $this->assertEquals(900.00, (float) $artigo->refresh()->cost);
    }

    /** O IVA não é custo para quem o deduz — não pode inflar a margem. */
    public function test_imposto_fica_de_fora_do_custo(): void
    {
        $artigo = $this->artigo(500);

        $this->comprar([[
            'product_id'   => $artigo->id,
            'product_name' => $artigo->name,
            'quantity'     => 1,
            'unit_price'   => 1000,
            'tax_rate'     => 14,
            'tax_amount'   => 140,
            'total'        => 1140,
        ]]);

        $this->assertEquals(1000.00, (float) $artigo->refresh()->cost);
    }

    /** Um rascunho ainda pode ser corrigido: não mexe no catálogo. */
    public function test_rascunho_nao_mexe_no_custo(): void
    {
        $artigo = $this->artigo(500);

        $this->comprar([[
            'product_id'   => $artigo->id,
            'product_name' => $artigo->name,
            'quantity'     => 1,
            'unit_price'   => 750,
        ]], 'draft');

        $this->assertEquals(500.00, (float) $artigo->refresh()->cost);
    }

    /** Preço zero é engano ou oferta — apagar o custo bom seria pior. */
    public function test_preco_zero_nao_apaga_o_custo(): void
    {
        $artigo = $this->artigo(500);

        $this->comprar([[
            'product_id'   => $artigo->id,
            'product_name' => $artigo->name,
            'quantity'     => 3,
            'unit_price'   => 0,
        ]]);

        $this->assertEquals(500.00, (float) $artigo->refresh()->cost);
    }

    /** Uma compra com vários artigos actualiza-os todos, cada um pelo seu. */
    public function test_actualiza_todos_os_artigos_da_compra(): void
    {
        $a = $this->artigo(100);
        $b = $this->artigo(200);

        $this->comprar([
            ['product_id' => $a->id, 'product_name' => $a->name, 'quantity' => 5, 'unit_price' => 130],
            ['product_id' => $b->id, 'product_name' => $b->name, 'quantity' => 2, 'unit_price' => 275],
        ]);

        $this->assertEquals(130.00, (float) $a->refresh()->cost);
        $this->assertEquals(275.00, (float) $b->refresh()->cost);
    }

    /** Corrigir o preço de uma compra já recebida corrige o custo. */
    public function test_corrigir_a_compra_corrige_o_custo(): void
    {
        $artigo = $this->artigo(500);

        $factura = $this->comprar([[
            'product_id'   => $artigo->id,
            'product_name' => $artigo->name,
            'quantity'     => 2,
            'unit_price'   => 750,
        ]]);

        $this->assertEquals(750.00, (float) $artigo->refresh()->cost);

        // O formulário de edição apaga as linhas e volta a escrevê-las, sem
        // mexer no estado — por isso o observer não corre e a correcção do
        // custo tem de vir do próprio formulário.
        $factura->items()->delete();
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $factura->id,
            'product_id'          => $artigo->id,
            'product_name'        => $artigo->name,
            'quantity'            => 2,
            'unit_price'          => 820,
            'discount_amount'     => 0,
            'subtotal'            => 1640,
            'total'               => 1640,
        ]);

        app(ActualizarCustoDeCompra::class)->aplicar($factura->fresh(['items.product']));

        $this->assertEquals(820.00, (float) $artigo->refresh()->cost);
    }

    /** Artigo de outra empresa nunca é tocado, mesmo com o id na linha. */
    public function test_nao_toca_em_artigo_de_outra_empresa(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = Product::withoutEvents(fn () => Product::create([
            'tenant_id' => $outra->id,
            'code'      => 'ALH-' . uniqid(),
            'name'      => 'Artigo Vizinho',
            'price'     => 1, 'cost' => 111, 'is_active' => true,
        ]));

        $this->comprar([[
            'product_id'   => $alheio->id,
            'product_name' => $alheio->name,
            'quantity'     => 1,
            'unit_price'   => 999,
        ]]);

        $this->assertEquals(111.00, (float) $alheio->refresh()->cost);
    }
}

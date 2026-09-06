<?php

namespace Tests\Feature;

use App\Models\Invoicing\ProductBatch;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * A API DOS LOTES, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: o lote nasce todo disponível;
 * corrigir o total não altera o que já saiu; um lote usado não se apaga; e
 * cada verbo tem a sua permissão.
 */
class ApiDosLotesParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/lotes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 100, 'cost' => 25,
            'unit' => 'un', 'tax_type' => 'isento', 'exemption_reason' => 'M99', 'manage_stock' => true, 'stock_quantity' => 0, 'track_batches' => true, 'is_active' => true,
        ]);
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'product_id' => $this->artigo()->id,
            'warehouse_id' => $this->armazem->id,
            'batch_number' => 'L-' . uniqid(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'quantity' => 100,
            'cost_price' => 25,
            'alert_days' => 30,
        ], $por);
    }

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();

        $this->comPermissoes('invoicing.stock.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_criar', false);
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /** Corrigir o total não altera o que já saiu. @test */
    public function corrigir_o_total_nao_altera_o_que_ja_saiu(): void
    {
        $this->comPermissoes('invoicing.stock.view', 'invoicing.product-batches.create', 'invoicing.product-batches.edit');

        $r = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();
        $lote = ProductBatch::find($r->json('data.id'));

        $this->assertEqualsWithDelta(100, $lote->quantity_available, 0.001, 'nasce todo disponível');

        // Saíram 60 (por facturas, movimentos): é um facto.
        $lote->update(['quantity_available' => 40]);

        $this->putJson(self::RAIZ . '/' . $lote->id, $this->corpo(['product_id' => $lote->product_id, 'quantity' => 90]))->assertOk();

        $this->assertEqualsWithDelta(30, $lote->fresh()->quantity_available, 0.001, '90 − 60, e não 90 × 0,4');
    }

    /** @test */
    public function um_lote_usado_nao_se_apaga(): void
    {
        $this->comPermissoes('invoicing.stock.view', 'invoicing.product-batches.create', 'invoicing.product-batches.delete');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');
        ProductBatch::find($id)->update(['quantity_available' => 99]);

        $this->deleteJson(self::RAIZ . '/' . $id)->assertStatus(422);
        $this->assertNotNull(ProductBatch::find($id));

        $livre = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');
        $this->deleteJson(self::RAIZ . '/' . $livre)->assertOk();
        $this->assertNull(ProductBatch::find($livre));
    }

    /** @test */
    public function a_validade_tem_de_vir_depois_do_fabrico(): void
    {
        $this->comPermissoes('invoicing.stock.view', 'invoicing.product-batches.create');

        $this->postJson(self::RAIZ, $this->corpo(['manufacturing_date' => '2026-05-01', 'expiry_date' => '2026-04-01']))
            ->assertStatus(422)->assertJsonValidationErrors('expiry_date');

        $lista = $this->getJson(self::RAIZ . '?estado=expiring_soon')->assertOk();
        $this->assertSame(0, $lista->json('meta.total'));
    }
}

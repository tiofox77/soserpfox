<?php

namespace Tests\Feature;

use App\Models\Invoicing\Waste;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * A API DAS QUEBRAS, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: registar faz o stock descer e
 * congela o custo; anular fá-lo voltar e tira a quebra do relatório; sem
 * stock que chegue recusa e diz onde; e ver é uma permissão, registar é outra.
 */
class ApiDasQuebrasParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/quebras';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigoComStock(float $qtd): Product
    {
        $p = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 100, 'cost' => 25,
            'unit' => 'un', 'tax_type' => 'isento', 'exemption_reason' => 'M99', 'manage_stock' => true, 'stock_quantity' => 0, 'is_active' => true,
        ]);

        $this->comPermissoes('invoicing.stock.edit');
        $this->postJson('/api/v1/invoicing/react/stock/entrada', [
            'armazem_id' => $this->armazem->id,
            'itens' => [['product_id' => $p->id, 'op' => 'add', 'quantity' => $qtd, 'unit_cost' => 25]],
        ])->assertCreated();

        return $p;
    }

    /** @test */
    public function ver_e_uma_permissao_registar_e_outra(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();

        $this->comPermissoes('invoicing.stock.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_registar', false)->assertJsonCount(6, 'motivos');
        $this->postJson(self::RAIZ, ['product_id' => 1, 'warehouse_id' => 1, 'quantity' => 1, 'reason' => 'perdido'])->assertForbidden();
    }

    /** @test */
    public function registar_faz_o_stock_descer_e_anular_fa_lo_voltar(): void
    {
        $this->comPermissoes('invoicing.stock.view');
        $p = $this->artigoComStock(10);

        $r = $this->postJson(self::RAIZ, ['product_id' => $p->id, 'warehouse_id' => $this->armazem->id, 'quantity' => 3, 'reason' => 'estragado', 'notes' => 'Caiu'])
            ->assertCreated();

        $this->assertEqualsWithDelta(7, $p->fresh()->stock_quantity, 0.001, 'o stock desceu');
        $this->assertEqualsWithDelta(75, $r->json('data.custo'), 0.01, 'o custo ficou congelado: 3 × 25');

        $lista = $this->getJson(self::RAIZ)->assertOk();
        $this->assertSame(1, $lista->json('resumo.registos'));
        $this->assertEqualsWithDelta(75, $lista->json('resumo.custo'), 0.01);
        $this->assertSame('estragado', $lista->json('resumo.por_motivo.0.motivo'));

        $this->postJson(self::RAIZ . '/' . $r->json('data.id') . '/anular', [])->assertOk();

        $this->assertEqualsWithDelta(10, $p->fresh()->stock_quantity, 0.001, 'o stock voltou');
        $this->assertSame(0, $this->getJson(self::RAIZ)->assertOk()->json('resumo.registos'), 'a anulada saiu do relatório');
        $this->assertNotNull(Waste::find($r->json('data.id'))->annulled_at);
    }

    /** @test */
    public function sem_stock_que_chegue_recusa_e_diz_onde(): void
    {
        $this->comPermissoes('invoicing.stock.view');
        $p = $this->artigoComStock(2);

        $this->postJson(self::RAIZ, ['product_id' => $p->id, 'warehouse_id' => $this->armazem->id, 'quantity' => 5, 'reason' => 'perdido'])
            ->assertStatus(422);

        $this->assertEqualsWithDelta(2, $p->fresh()->stock_quantity, 0.001);

        $this->postJson(self::RAIZ, ['product_id' => $p->id, 'warehouse_id' => $this->armazem->id, 'quantity' => 1, 'reason' => 'porque_sim'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
    }
}

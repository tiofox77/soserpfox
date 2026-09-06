<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Waste;
use App\Models\Product;
use App\Services\Invoicing\QuebraDeStock;
use InvalidArgumentException;
use Tests\TenantTestCase;

/**
 * As quebras de stock: expirado, estragado, partido, perdido.
 *
 * PORQUE EXISTEM. O artigo estragado ou saía do stock por um "ajuste" mudo,
 * ou ficava lá a fingir que existia — uma mentira esconde a perda, a outra
 * vende o que não há. O que estes ensaios prendem:
 *
 *   · a quebra passa PELO MOVIMENTO (as linhas são a fonte de verdade — a
 *     regra sagrada do stock desta casa);
 *   · o custo congela no momento e sobrevive a mudanças de preço;
 *   · nada se apaga: anular é o movimento contrário.
 *
 * O ecrã é hoje o React `Quebras.tsx`, servido pela API
 * `/api/v1/invoicing/react/quebras`; a regra continua no `QuebraDeStock`, que
 * é onde a maior parte destes ensaios bate.
 */
class QuebrasDeStockTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/quebras';

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.stock.view');

        $this->produto = Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'produto',
            'name' => 'Água 1,5L',
            'price' => 500,
            'cost' => 200,
            'unit' => 'UN',
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }

    private function servico(): QuebraDeStock
    {
        return app(QuebraDeStock::class);
    }

    /**
     * Dá entrada de stock pelo caminho normal (um movimento `in`): não se
     * quebra o que não há, e o observer recusa — e faz bem.
     */
    private function comStock(float $qtd): void
    {
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $this->produto->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => $qtd,
            'reference_type' => 'ensaio',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function registar_cria_a_quebra_e_o_movimento_de_saida(): void
    {
        $this->comStock(100);

        $quebra = $this->servico()->registar([
            'product_id' => $this->produto->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => 6,
            'reason' => 'expirado',
            'notes' => 'Lote de Junho',
        ], $this->tenant->id, $this->user->id);

        // A REGRA DO STOCK: a quebra existe COMO movimento — nunca como um
        // decremento à mão.
        $movimento = StockMovement::withoutGlobalScopes()->find($quebra->stock_movement_id);

        $this->assertNotNull($movimento);
        $this->assertSame('out', $movimento->type);
        $this->assertSame('quebra', $movimento->reference_type);
        $this->assertEqualsWithDelta(6, (float) $movimento->quantity, 0.001);

        $this->assertSame('expirado', $quebra->reason);
        $this->assertSame('Lote de Junho', $quebra->notes);
    }

    /**
     * O CUSTO CONGELA: mudar o custo do artigo depois não reescreve a perda.
     *
     * @test
     */
    public function o_custo_congela_no_momento_da_quebra(): void
    {
        $this->comStock(100);

        $quebra = $this->servico()->registar([
            'product_id' => $this->produto->id,
            'quantity' => 3,
            'reason' => 'estragado',
        ], $this->tenant->id, $this->user->id);

        $this->assertSame('200.00', (string) $quebra->unit_cost);
        $this->assertSame('600.00', (string) $quebra->total_cost);

        $this->produto->update(['cost' => 999]);

        $this->assertSame('600.00', (string) $quebra->fresh()->total_cost,
            'a perda é a do momento — o relatório não pode mudar com o preço de hoje');
    }

    /** Um serviço não tem stock para quebrar. */
    public function test_um_servico_nao_se_quebra(): void
    {
        $servico = Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'servico',
            'name' => 'Consultoria',
            'price' => 10000,
            'cost' => 0,
            'unit' => 'UN',
            'manage_stock' => false,
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->servico()->registar([
            'product_id' => $servico->id,
            'quantity' => 1,
            'reason' => 'outro',
        ], $this->tenant->id, $this->user->id);
    }

    /** Motivo fora da lista fechada é recusado — cem «outros» não ensinam nada. */
    public function test_motivo_inventado_e_recusado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servico()->registar([
            'product_id' => $this->produto->id,
            'quantity' => 1,
            'reason' => 'apeteceu',
        ], $this->tenant->id, $this->user->id);
    }

    /**
     * ANULAR É O MOVIMENTO CONTRÁRIO — nada se apaga.
     *
     * @test
     */
    public function anular_devolve_o_stock_pelo_movimento_contrario(): void
    {
        $this->comStock(100);

        $quebra = $this->servico()->registar([
            'product_id' => $this->produto->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => 4,
            'reason' => 'partido',
        ], $this->tenant->id, $this->user->id);

        $anulada = $this->servico()->anular($quebra, $this->tenant->id, $this->user->id);

        $this->assertTrue($anulada->anulada());

        $reverso = StockMovement::withoutGlobalScopes()->find($anulada->reversal_movement_id);

        $this->assertSame('in', $reverso->type);
        $this->assertSame('quebra_anulada', $reverso->reference_type);
        $this->assertEqualsWithDelta(4, (float) $reverso->quantity, 0.001);

        // O registo continua lá: uma perda apagada era escondida duas vezes.
        $this->assertDatabaseHas('invoicing_wastes', ['id' => $quebra->id]);

        // E anular duas vezes não cria segundo reverso.
        $this->servico()->anular($anulada->fresh(), $this->tenant->id, $this->user->id);
        $this->assertSame(1, StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'quebra_anulada')->count());
    }

    /**
     * O ecrã regista pela linha rápida.
     *
     * A quantidade com vírgula decimal («2,5») era lida pelo componente
     * Livewire; hoje é o `Quebras.tsx` que a converte antes de a mandar, e a
     * API recebe um número. As duas metades da regra provam-se aqui: a API
     * grava o decimal certo, e o ecrã continua a traduzir a vírgula.
     */
    public function test_o_ecra_regista_uma_quebra(): void
    {
        $this->comPermissoes('invoicing.stock.edit');
        $this->comStock(100);

        $this->postJson(self::RAIZ, [
            'product_id' => $this->produto->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => 2.5,
            'reason' => 'estragado',
        ])->assertCreated();

        $this->assertDatabaseHas('invoicing_wastes', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->produto->id,
            'reason' => 'estragado',
        ]);

        $quebra = Waste::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertEqualsWithDelta(2.5, (float) $quebra->quantity, 0.001, 'a vírgula decimal tem de ser lida certa');

        $ecra = file_get_contents(base_path('resources/js/ecras/facturacao/Quebras.tsx'));
        $this->assertStringContainsString("replace(',', '.')", $ecra, 'o ecrã deixou de traduzir a vírgula decimal');
    }

    /**
     * O RELATÓRIO: soma por motivo, e as anuladas ficam de fora — já não são
     * perdas.
     *
     * @test
     */
    public function o_relatorio_soma_por_motivo_e_ignora_anuladas(): void
    {
        $this->comStock(100);

        $this->servico()->registar(['product_id' => $this->produto->id, 'quantity' => 2, 'reason' => 'expirado'], $this->tenant->id, $this->user->id);
        $this->servico()->registar(['product_id' => $this->produto->id, 'quantity' => 1, 'reason' => 'expirado'], $this->tenant->id, $this->user->id);
        $anulavel = $this->servico()->registar(['product_id' => $this->produto->id, 'quantity' => 10, 'reason' => 'perdido'], $this->tenant->id, $this->user->id);
        $this->servico()->anular($anulavel, $this->tenant->id, $this->user->id);

        $resumo = $this->getJson(self::RAIZ)->assertOk()->json('resumo');

        // 3 unidades expiradas × 200 = 600; a perdida (anulada) fica de fora.
        $this->assertSame(2, $resumo['registos']);
        $this->assertEqualsWithDelta(600, $resumo['custo'], 0.01);

        $porMotivo = collect($resumo['por_motivo']);
        $this->assertEqualsWithDelta(600, $porMotivo->firstWhere('motivo', 'expirado')['custo'], 0.01);
        $this->assertNull($porMotivo->firstWhere('motivo', 'perdido'),
            'uma quebra anulada não pode continuar a contar como perda');
    }

    /** Sem a permissão de stock, o ecrã não abre. */
    public function test_sem_permissao_nao_abre(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->get('/invoicing/quebras')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Inventario;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use App\Services\Invoicing\ContagemFisica;
use InvalidArgumentException;
use Tests\TenantTestCase;

/**
 * A contagem física — o coração do módulo Inventário.
 *
 * O que estes ensaios prendem é o que pode custar dinheiro em silêncio:
 *
 *   · o esperado congela À ABERTURA (as vendas continuam enquanto se conta);
 *   · cada diferença fecha por um movimento `adjustment` verdadeiro, cuja
 *     quantidade é o VALOR FINAL — a semântica documentada do ajuste;
 *   · o que fica em branco NÃO é zero — é «não fui lá ver», e fica de fora.
 */
class ContagemFisicaTest extends TenantTestCase
{
    private Product $agua;

    private Product $vinho;

    private const RAIZ = '/api/v1/invoicing/react/inventario';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('inventario');
        $this->comPermissoes('inventario.view', 'inventario.contagem.manage');

        $this->agua = $this->produto('Água 1,5L', 200);
        $this->vinho = $this->produto('Vinho tinto', 3500);

        // Stock inicial pelo caminho normal: 50 águas, 10 vinhos.
        $this->entrada($this->agua, 50);
        $this->entrada($this->vinho, 10);
    }

    private function servico(): ContagemFisica
    {
        return app(ContagemFisica::class);
    }

    /** @test */
    public function abrir_congela_a_fotografia_do_sistema(): void
    {
        $contagem = $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);

        $this->assertSame('open', $contagem->status);

        $linhaAgua = $contagem->items()->where('product_id', $this->agua->id)->firstOrFail();
        $this->assertEqualsWithDelta(50, (float) $linhaAgua->expected_quantity, 0.001);
        $this->assertNull($linhaAgua->counted_quantity, 'nasce por contar, não a zero');

        // VENDE-SE ENQUANTO SE CONTA: o esperado da contagem NÃO mexe.
        StockMovement::create([
            'tenant_id' => $this->tenant->id, 'warehouse_id' => $this->armazem->id,
            'product_id' => $this->agua->id, 'type' => 'out', 'quantity' => 5,
            'reference_type' => 'ensaio', 'user_id' => $this->user->id,
        ]);

        $this->assertEqualsWithDelta(50, (float) $linhaAgua->fresh()->expected_quantity, 0.001,
            'o esperado congelou à abertura — comparar com um sistema em movimento dava diferenças de fantasma');
    }

    /** Duas contagens abertas no mesmo armazém fechavam a mesma diferença duas vezes. */
    public function test_um_armazem_so_tem_uma_contagem_aberta(): void
    {
        $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);
    }

    /**
     * A PROVA CENTRAL: fechar acerta o stock AO CONTADO, por movimento.
     *
     * @test
     */
    public function fechar_acerta_o_stock_ao_contado_por_movimento(): void
    {
        $contagem = $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);

        // Contaram-se 47 águas (faltam 3) e os 10 vinhos certos.
        $this->servico()->contar($contagem, $this->agua->id, 47, $this->tenant->id, $this->user->id);
        $this->servico()->contar($contagem, $this->vinho->id, 10, $this->tenant->id, $this->user->id);

        $fechada = $this->servico()->fechar($contagem, $this->tenant->id, $this->user->id);

        $this->assertSame('closed', $fechada->status);
        $this->assertSame(2, (int) $fechada->items_counted);
        $this->assertSame(1, (int) $fechada->items_adjusted, 'o vinho bateu certo — não se ajusta');
        $this->assertEqualsWithDelta(600, (float) $fechada->adjustment_cost, 0.01, '3 águas × 200 de custo');

        // O agregado ficou NO CONTADO.
        $saldo = Stock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->armazem->id)
            ->where('product_id', $this->agua->id)->value('quantity');

        $this->assertEqualsWithDelta(47, (float) $saldo, 0.001);

        // E existe o movimento que conta a história.
        $movimento = StockMovement::where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'contagem')
            ->where('product_id', $this->agua->id)->firstOrFail();

        $this->assertSame('adjustment', $movimento->type);
        $this->assertEqualsWithDelta(47, (float) $movimento->quantity, 0.001, 'no ajuste, a quantidade é o valor FINAL');
        $this->assertEqualsWithDelta(50, (float) $movimento->balance_before, 0.001);
    }

    /**
     * EM BRANCO NÃO É ZERO. Acertar a zero o que ninguém foi ver era
     * inventar uma perda gigante por preguiça do software.
     *
     * @test
     */
    public function o_que_fica_em_branco_nao_e_acertado(): void
    {
        $contagem = $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);

        // Só a água foi contada; o vinho ficou em branco.
        $this->servico()->contar($contagem, $this->agua->id, 50, $this->tenant->id, $this->user->id);

        $this->servico()->fechar($contagem, $this->tenant->id, $this->user->id);

        $saldoVinho = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->vinho->id)->value('quantity');

        $this->assertEqualsWithDelta(10, (float) $saldoVinho, 0.001, 'o vinho não foi contado — o stock dele não mexe');
    }

    /** Zero ESCRITO é uma decisão: a prateleira está mesmo vazia. */
    public function test_zero_escrito_acerta_a_zero(): void
    {
        $contagem = $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);

        $this->servico()->contar($contagem, $this->vinho->id, 0, $this->tenant->id, $this->user->id);
        $this->servico()->fechar($contagem, $this->tenant->id, $this->user->id);

        $this->assertEqualsWithDelta(0, (float) Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->vinho->id)->value('quantity'), 0.001);
    }

    /** Cancelar não mexe em NADA. */
    public function test_cancelar_nao_mexe_no_stock(): void
    {
        $contagem = $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);
        $this->servico()->contar($contagem, $this->agua->id, 1, $this->tenant->id, $this->user->id);

        $this->servico()->cancelar($contagem, $this->tenant->id);

        $this->assertEqualsWithDelta(50, (float) Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->agua->id)->value('quantity'), 0.001);
        $this->assertSame(0, StockMovement::where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'contagem')->count());
    }

    /** Fechar sem nada contado é recusado. */
    public function test_fechar_sem_nada_contado_e_recusado(): void
    {
        $contagem = $this->servico()->abrir($this->armazem->id, $this->tenant->id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->servico()->fechar($contagem, $this->tenant->id, $this->user->id);
    }

    /** O ciclo inteiro pela porta: abrir, contar, ver o resumo, fechar. */
    public function test_o_ecra_abre_conta_e_fecha(): void
    {
        $id = (int) $this->actingAs($this->user)->postJson(self::RAIZ.'/contagem', [
            'warehouse_id' => $this->armazem->id,
        ])->assertCreated()->json('data.id');

        // UMA CONTAGEM ABERTA RETOMA-SE SOZINHA: quem volta ao ecrã encontra-a.
        $this->actingAs($this->user)->getJson(self::RAIZ.'/contagem')
            ->assertOk()
            ->assertJsonPath('aberta.id', $id);

        $this->actingAs($this->user)->postJson(self::RAIZ."/contagem/{$id}/contar", [
            'product_id' => $this->agua->id,
            'contado' => 48,
        ])->assertOk()->assertJsonPath('diferenca', -2);

        // O RESUMO ANTES DO BOTÃO: quantos acertos e quanto custam.
        $resumo = $this->actingAs($this->user)->getJson(self::RAIZ."/contagem/{$id}/resumo")
            ->assertOk()->json();

        $this->assertSame(1, $resumo['contados']);
        $this->assertSame(1, $resumo['acertos']);
        $this->assertEqualsWithDelta(400, $resumo['custo'], 0.01, '2 águas × 200 Kz');

        $this->actingAs($this->user)->postJson(self::RAIZ."/contagem/{$id}/fechar")->assertOk();

        $this->assertEqualsWithDelta(48, (float) Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->agua->id)->value('quantity'), 0.001);

        // E o ecrã volta ao modo «sem contagem aberta».
        $this->actingAs($this->user)->getJson(self::RAIZ.'/contagem')
            ->assertOk()->assertJsonPath('aberta', null);
    }

    /**
     * NULO É «AINDA NÃO CONTEI», e não «contei zero».
     *
     * São coisas diferentes: a primeira não gera acerto nenhum; a segunda apaga
     * o stock do artigo. Escrever e apagar a caixa tem de voltar ao primeiro.
     */
    public function test_apagar_o_contado_nao_e_contar_zero(): void
    {
        $id = (int) $this->actingAs($this->user)->postJson(self::RAIZ.'/contagem', [
            'warehouse_id' => $this->armazem->id,
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->user)->postJson(self::RAIZ."/contagem/{$id}/contar", [
            'product_id' => $this->agua->id, 'contado' => 0,
        ])->assertOk()->assertJsonPath('contado', 0);

        $this->actingAs($this->user)->postJson(self::RAIZ."/contagem/{$id}/contar", [
            'product_id' => $this->agua->id, 'contado' => null,
        ])->assertOk()->assertJsonPath('contado', null);

        $this->assertSame(0, $this->actingAs($this->user)
            ->getJson(self::RAIZ."/contagem/{$id}/resumo")->assertOk()->json('contados'));
    }

    /** Os três ecrãs do módulo abrem e montam o React. */
    public function test_os_ecras_do_inventario_abrem(): void
    {
        foreach ([
            '/inventario/dashboard' => 'inventario/painel',
            '/inventario/movimentos' => 'inventario/movimentos',
            '/inventario/contagem' => 'inventario/contagem',
        ] as $rota => $ecra) {
            $this->actingAs($this->user)->get($rota)->assertOk()->assertSee($ecra, false);
        }
    }

    /** O painel soma o valor e conta os negativos. */
    public function test_o_painel_conta_o_que_importa(): void
    {
        $resumo = $this->actingAs($this->user)->getJson(self::RAIZ.'/painel')->assertOk()->json('resumo');

        // 50 águas × 200 + 10 vinhos × 3500 = 45.000
        $this->assertEqualsWithDelta(45000, $resumo['valor'], 0.01);
        $this->assertSame(0, $resumo['negativos']);
    }

    /** Sem a permissão de contagem, a contagem não abre — os outros sim. */
    public function test_a_contagem_exige_a_permissao_propria(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $outro->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'inventario.view', 'guard_name' => 'web']
        ));

        $this->actingAs($outro->fresh())->get('/inventario/dashboard')->assertOk();
        $this->actingAs($outro->fresh())->get('/inventario/contagem')->assertForbidden();
    }

    /* ── apoios ─────────────────────────────────────────────────────── */

    private function produto(string $nome, float $custo): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'produto',
            'name' => $nome,
            'price' => $custo * 2,
            'cost' => $custo,
            'unit' => 'UN',
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }

    private function entrada(Product $p, float $qtd): void
    {
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $p->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => $qtd,
            'reference_type' => 'ensaio',
            'user_id' => $this->user->id,
        ]);
    }
}

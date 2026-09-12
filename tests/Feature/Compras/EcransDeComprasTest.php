<?php

namespace Tests\Feature\Compras;

use App\Models\Compras\Requisicao;
use App\Models\Supplier;
use App\Services\Compras\FluxoDaRequisicao;
use Tests\TenantTestCase;

/**
 * Os ecrãs das Compras: que abrem, que criam, e sobretudo que NÃO deixam
 * aprovar nem receber quem não tem autoridade para isso.
 */
class EcransDeComprasTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/compras';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('compras');
    }

    private function fornecedor(): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor Ecrã',
            'type' => 'pessoa_juridica',
            'is_active' => true,
        ]);
    }

    private function requisicaoSubmetida(): Requisicao
    {
        $produto = $this->produtoComStock(0);
        $fluxo = app(FluxoDaRequisicao::class);

        $req = $fluxo->criar($this->tenant->id, $this->user->id, [], [
            ['product_id' => $produto->id, 'descricao' => $produto->name, 'quantidade' => 4],
        ]);

        return $fluxo->submeter($req, $this->tenant->id);
    }

    /** @test */
    public function os_tres_ecras_abrem(): void
    {
        $this->comPermissoes('compras.view', 'compras.requisicoes.view', 'compras.encomendas.view');

        $this->get(route('compras.dashboard'))->assertOk();
        $this->get(route('compras.requisicoes'))->assertOk();
        $this->get(route('compras.encomendas'))->assertOk();
    }

    /** Sem a permissão de ver, o ecrã não abre. */
    public function test_sem_permissao_o_ecra_fecha(): void
    {
        $this->comPermissoes('compras.view');

        $this->get(route('compras.requisicoes'))->assertForbidden();
        $this->get(route('compras.encomendas'))->assertForbidden();
    }

    /** @test */
    public function criar_uma_requisicao_pelo_ecra(): void
    {
        $this->comPermissoes('compras.requisicoes.view', 'compras.requisicoes.manage');
        $produto = $this->produtoComStock(0);

        $this->postJson(self::RAIZ.'/requisicoes', [
            'justificacao' => 'Acabou na bancada',
            'linhas' => [[
                'product_id' => $produto->id,
                'descricao' => $produto->name,
                'quantidade' => 6,
            ]],
        ])->assertCreated();

        $req = Requisicao::where('tenant_id', $this->tenant->id)->latest()->first();

        $this->assertNotNull($req);
        $this->assertSame('rascunho', $req->estado);
        $this->assertSame('Acabou na bancada', $req->justificacao);
        $this->assertSame(6.0, (float) $req->itens->first()->quantidade);
        $this->assertStringStartsWith('REQ-', $req->numero);
    }

    /**
     * QUEM PEDE NÃO É QUEM APROVA.
     *
     * Sem `compras.requisicoes.decidir`, carregar em aprovar não muda nada —
     * mesmo que o botão não estivesse escondido na vista.
     *
     * @test
     */
    public function sem_autoridade_nao_se_aprova(): void
    {
        $this->comPermissoes('compras.requisicoes.view', 'compras.requisicoes.manage');
        $req = $this->requisicaoSubmetida();

        $this->postJson(self::RAIZ."/requisicoes/{$req->id}/aprovar")->assertForbidden();

        $this->assertSame('submetida', $req->fresh()->estado);
    }

    /** Com autoridade, aprova. */
    public function test_com_autoridade_aprova(): void
    {
        $this->comPermissoes('compras.requisicoes.view', 'compras.requisicoes.decidir');
        $req = $this->requisicaoSubmetida();

        $this->postJson(self::RAIZ."/requisicoes/{$req->id}/aprovar")->assertOk();

        $this->assertSame('aprovada', $req->fresh()->estado);
        $this->assertSame($this->user->id, $req->fresh()->decidida_por);
    }

    /** Recusar pelo ecrã: abre a caixa do motivo e grava-o. */
    public function test_recusar_pelo_ecra_guarda_o_motivo(): void
    {
        $this->comPermissoes('compras.requisicoes.view', 'compras.requisicoes.decidir');
        $req = $this->requisicaoSubmetida();

        // O MOTIVO É OBRIGATÓRIO: sem ele, quem pediu não sabe porquê e volta
        // a pedir o mesmo na semana seguinte.
        $this->postJson(self::RAIZ."/requisicoes/{$req->id}/rejeitar", ['motivo' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->postJson(self::RAIZ."/requisicoes/{$req->id}/rejeitar", [
            'motivo' => 'Há de sobra no armazém 2',
        ])->assertOk();

        $this->assertSame('rejeitada', $req->fresh()->estado);
        $this->assertSame('Há de sobra no armazém 2', $req->fresh()->motivo_recusa);
    }

    /**
     * Receber mercadoria dá entrada de stock — sem a permissão própria, não
     * entra nada.
     *
     * @test
     */
    public function sem_autoridade_nao_se_recebe(): void
    {
        $this->comPermissoes('compras.encomendas.view', 'compras.encomendas.manage');
        $produto = $this->produtoComStock(3);

        $enc = app(\App\Services\Compras\FluxoDaEncomenda::class)->criar(
            $this->tenant->id, $this->user->id,
            ['supplier_id' => $this->fornecedor()->id, 'warehouse_id' => $this->armazem->id],
            [['product_id' => $produto->id, 'descricao' => $produto->name, 'quantidade' => 9, 'preco_unitario' => 100]]
        );
        app(\App\Services\Compras\FluxoDaEncomenda::class)->enviar($enc, $this->tenant->id);

        $this->postJson(self::RAIZ."/encomendas/{$enc->id}/receber", [
            'quantidades' => [$enc->itens->first()->id => 9],
        ])->assertForbidden();

        $this->assertSame(3.0, (float) \App\Models\Invoicing\Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $produto->id)->value('quantity'));
        $this->assertSame('enviada', $enc->fresh()->estado);
    }

    /** O painel conta o que está à espera de alguém. */
    public function test_o_painel_conta_o_que_espera(): void
    {
        $this->comPermissoes('compras.view');
        $this->requisicaoSubmetida();

        $r = $this->getJson(self::RAIZ.'/painel')->assertOk()->json('resumo');

        $this->assertSame(1, $r['por_decidir']);
    }
}

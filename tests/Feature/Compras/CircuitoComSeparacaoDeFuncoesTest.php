<?php

namespace Tests\Feature\Compras;

use App\Models\Compras\DefinicoesDasCompras;
use App\Models\Compras\Encomenda;
use App\Models\Compras\PedidoDePagamento;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Treasury\Account;
use App\Models\Treasury\Bank;
use App\Models\Treasury\Transaction;
use App\Models\User;
use App\Services\Compras\FluxoDaEncomenda;
use App\Services\Compras\FluxoDaRequisicao;
use App\Services\Compras\FluxoDoPagamento;
use Tests\TenantTestCase;

/**
 * O CIRCUITO DAS COMPRAS COM SEPARAÇÃO DE FUNÇÕES (27/09/2026).
 *
 * «Requisição → Encomenda → Solicitação de Pagamento → Pagamento → Recepção →
 * Fatura», cada passo com o seu responsável e o dinheiro a sair da tesouraria.
 * O que estes ensaios prendem: quem faz não aprova, não se pede nem se paga
 * mais do que se deve, o pagamento é um movimento de tesouraria de verdade, e
 * o que se pagou antes da factura chega à factura — sem pagar duas vezes.
 */
class CircuitoComSeparacaoDeFuncoesTest extends TenantTestCase
{
    private const PEDIR = ['compras.view', 'compras.encomendas.view', 'compras.encomendas.manage', 'compras.pagamentos.solicitar'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('compras');
        $this->comModulo('treasury');
    }

    // ─── As peças ────────────────────────────────────────────────────────

    private function pessoa(string ...$permissoes): User
    {
        $u = User::create([
            'name' => 'Pessoa '.uniqid(), 'email' => 'p'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        setPermissionsTeamId($this->tenant->id);
        foreach ($permissoes as $nome) {
            \Spatie\Permission\Models\Permission::findOrCreate($nome, 'web');
        }
        $u->givePermissionTo($permissoes);
        $u->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    private function regras(int $encomenda = 0, int $pagamento = 0): void
    {
        DefinicoesDasCompras::updateOrCreate(['tenant_id' => $this->tenant->id], [
            'aprovacoes_encomenda' => $encomenda, 'aprovacoes_pagamento' => $pagamento,
        ]);
    }

    private function conta(float $saldo = 1_000_000): Account
    {
        $banco = Bank::firstOrCreate(['code' => 'BFA'], ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]);

        return Account::create([
            'tenant_id' => $this->tenant->id, 'bank_id' => $banco->id,
            'account_name' => 'Conta '.uniqid(), 'account_number' => (string) random_int(100000, 999999),
            'currency' => 'AOA', 'initial_balance' => $saldo, 'current_balance' => $saldo, 'is_active' => true,
        ]);
    }

    private function encomenda(float $qtd = 10, float $preco = 1000, ?Product $produto = null): Encomenda
    {
        $produto ??= $this->produtoComStock(0);

        $fornecedor = Supplier::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fornecedor '.uniqid(), 'type' => 'pessoa_juridica', 'is_active' => true,
        ]);

        return app(FluxoDaEncomenda::class)->criar($this->tenant->id, $this->user->id, [
            'supplier_id' => $fornecedor->id, 'warehouse_id' => $this->armazem->id,
        ], [
            ['product_id' => $produto->id, 'descricao' => $produto->name, 'quantidade' => $qtd, 'preco_unitario' => $preco],
        ]);
    }

    /** A encomenda enviada, com o total conhecido (sem imposto não sabemos a taxa do artigo de ensaio). */
    private function enviada(float $qtd = 10, float $preco = 1000): Encomenda
    {
        $e = $this->encomenda($qtd, $preco);
        app(FluxoDaEncomenda::class)->enviar($e, $this->tenant->id);

        return $e->fresh();
    }

    // ─── A requisição sem preço ──────────────────────────────────────────

    /** Quem pede não sabe o preço: a encomenda nasce com o último custo do artigo, e quem compra acerta. */
    public function test_a_requisicao_nao_pede_custo_e_a_encomenda_sugere_o_custo_do_artigo(): void
    {
        $produto = $this->produtoComStock(0);
        $produto->update(['cost' => 750]);

        $req = app(FluxoDaRequisicao::class)->criar($this->tenant->id, $this->user->id, ['warehouse_id' => $this->armazem->id, 'justificacao' => 'Acabou o stock'], [
            ['product_id' => $produto->id, 'descricao' => $produto->name, 'quantidade' => 4],
        ]);
        $this->assertNull($req->itens->first()->custo_estimado);

        app(FluxoDaRequisicao::class)->submeter($req, $this->tenant->id);
        app(FluxoDaRequisicao::class)->aprovar($req->fresh(), $this->tenant->id, $this->pessoa()->id);

        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'F', 'type' => 'pessoa_juridica', 'is_active' => true]);
        $enc = app(FluxoDaEncomenda::class)->daRequisicao($req->fresh(), $this->tenant->id, $this->user->id, $fornecedor->id);

        $this->assertSame(750.0, (float) $enc->itens->first()->preco_unitario);
    }

    // ─── A aprovação da encomenda ────────────────────────────────────────

    public function test_sem_regras_a_encomenda_vai_do_rascunho_ao_fornecedor_como_sempre(): void
    {
        $e = $this->encomenda();

        app(FluxoDaEncomenda::class)->enviar($e, $this->tenant->id);
        $this->assertSame('enviada', $e->fresh()->estado);

        $this->expectExceptionMessage('não exige aprovação');
        app(FluxoDaEncomenda::class)->pedirAprovacao($this->encomenda(), $this->tenant->id);
    }

    public function test_com_duas_aprovacoes_quem_fez_nao_aprova_e_so_sai_com_os_dois_sins(): void
    {
        $this->regras(encomenda: 2);
        $fluxo = app(FluxoDaEncomenda::class);
        $e = $this->encomenda();

        try {
            $fluxo->enviar($e, $this->tenant->id);
            $this->fail('um rascunho não sai sem aprovação');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('precisa de ser aprovada', $ex->getMessage());
        }

        $fluxo->pedirAprovacao($e, $this->tenant->id);
        $this->assertSame('em_aprovacao', $e->fresh()->estado);

        try {
            $fluxo->decidir($e->fresh(), $this->tenant->id, $this->user, true, null);
            $this->fail('quem fez a encomenda não a aprova');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('não a aprova', $ex->getMessage());
        }

        $ana = $this->pessoa('compras.encomendas.aprovar');
        $bruno = $this->pessoa('compras.encomendas.aprovar');

        $fluxo->decidir($e->fresh(), $this->tenant->id, $ana, true, null);
        $this->assertSame('em_aprovacao', $e->fresh()->estado, 'um sim de dois não chega');

        try {
            $fluxo->decidir($e->fresh(), $this->tenant->id, $ana, true, null);
            $this->fail('um voto por pessoa');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('Já deu a sua decisão', $ex->getMessage());
        }

        $fluxo->decidir($e->fresh(), $this->tenant->id, $bruno, true, 'Preço confirmado');
        $this->assertSame('aprovada', $e->fresh()->estado);

        $fluxo->enviar($e->fresh(), $this->tenant->id);
        $this->assertSame('enviada', $e->fresh()->estado);
    }

    public function test_recusar_exige_motivo_e_volta_ao_rascunho_com_ronda_nova(): void
    {
        $this->regras(encomenda: 1);
        $fluxo = app(FluxoDaEncomenda::class);
        $e = $this->encomenda();
        $fluxo->pedirAprovacao($e, $this->tenant->id);
        $ana = $this->pessoa('compras.encomendas.aprovar');

        try {
            $fluxo->decidir($e->fresh(), $this->tenant->id, $ana, false, '  ');
            $this->fail('recusar sem motivo');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('porque está a recusar', $ex->getMessage());
        }

        $fluxo->decidir($e->fresh(), $this->tenant->id, $ana, false, 'Fornecedor mais caro que o habitual');
        $e->refresh();
        $this->assertSame('rascunho', $e->estado);
        $this->assertSame('Fornecedor mais caro que o habitual', $e->motivo_recusa);

        // Corrigida e reenviada: ronda nova, a Ana vota outra vez.
        $fluxo->pedirAprovacao($e, $this->tenant->id);
        $fluxo->decidir($e->fresh(), $this->tenant->id, $ana, true, null);
        $this->assertSame('aprovada', $e->fresh()->estado);
    }

    // ─── O pedido de pagamento ───────────────────────────────────────────

    public function test_nao_se_pede_pagamento_de_um_rascunho_nem_mais_do_que_se_deve(): void
    {
        $pagamentos = app(FluxoDoPagamento::class);

        try {
            $pagamentos->pedir($this->encomenda(), $this->tenant->id, $this->user->id, ['valor' => 100]);
            $this->fail('num rascunho o preço ainda pode mudar');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('Envie primeiro', $ex->getMessage());
        }

        $e = $this->enviada();
        $total = (float) $e->total;

        // Em parcelas: um sinal e o resto.
        $sinal = $pagamentos->pedir($e, $this->tenant->id, $this->user->id, ['valor' => round($total / 2, 2)]);
        $this->assertSame('por_pagar', $sinal->estado, 'sem aprovação exigida vai direito à tesouraria');
        $pagamentos->pedir($e->fresh(), $this->tenant->id, $this->user->id, ['valor' => round($total - $total / 2, 2)]);

        $this->expectExceptionMessage('já tem pedido de pagamento pelo valor inteiro');
        $pagamentos->pedir($e->fresh(), $this->tenant->id, $this->user->id, ['valor' => 1]);
    }

    /** O tesoureiro paga: o dinheiro sai da conta, com recibo de compra e movimento de saída. */
    public function test_pagar_faz_sair_o_dinheiro_da_tesouraria_uma_vez_so(): void
    {
        $conta = $this->conta(100_000);
        $e = $this->enviada(10, 1000);
        $p = app(FluxoDoPagamento::class)->pedir($e, $this->tenant->id, $this->user->id, ['valor' => 4000]);
        $tesoureiro = $this->pessoa('treasury.pagamentos.pagar');

        app(FluxoDoPagamento::class)->pagar($p, $this->tenant->id, $tesoureiro, ['forma' => 'transfer', 'account_id' => $conta->id, 'referencia' => 'TRF-123']);

        $p->refresh();
        $this->assertSame('pago', $p->estado);
        $this->assertSame($tesoureiro->id, (int) $p->pago_por);

        $recibo = Receipt::withoutGlobalScopes()->findOrFail($p->receipt_id);
        $this->assertSame('purchase', $recibo->type);
        $this->assertNull($recibo->purchase_invoice_id, 'antes da factura é um adiantamento');
        $this->assertSame(4000.0, (float) $recibo->amount_paid);

        $mov = Transaction::withoutGlobalScopes()->findOrFail($p->transaction_id);
        $this->assertSame('expense', $mov->type);
        $this->assertSame('supplier_payment', $mov->category);
        $this->assertSame($conta->id, (int) $mov->account_id);
        $this->assertSame(96_000.0, (float) $conta->fresh()->current_balance, 'o dinheiro saiu da conta');

        // Dois cliques não pagam duas vezes.
        $this->expectExceptionMessage('já foi pago');
        app(FluxoDoPagamento::class)->pagar($p, $this->tenant->id, $tesoureiro, ['forma' => 'transfer', 'account_id' => $conta->id]);
    }

    /** Pago antes da factura, o dinheiro chega à factura quando ela é emitida — e a dívida nasce paga. */
    public function test_o_adiantamento_liga_se_a_factura_quando_ela_e_emitida(): void
    {
        $conta = $this->conta();
        $e = $this->enviada(10, 1000);
        $fluxo = app(FluxoDaEncomenda::class);
        $pagamentos = app(FluxoDoPagamento::class);

        $p = $pagamentos->pedir($e, $this->tenant->id, $this->user->id, ['valor' => 3000]);
        $p = $pagamentos->pagar($p, $this->tenant->id, $this->pessoa('treasury.pagamentos.pagar'), ['forma' => 'transfer', 'account_id' => $conta->id]);

        $item = $e->itens()->first();
        $fluxo->receber($e->fresh(), $this->tenant->id, $this->user->id, [$item->id => 10]);
        $factura = $fluxo->facturar($e->fresh(), $this->tenant->id, $this->user->id);

        $this->assertSame(0.0, (float) $factura->fresh()->paid_amount, 'em rascunho ainda não conta');

        // Emitida (sai de rascunho): o adiantamento liga-se.
        $factura->update(['status' => 'pending']);

        $factura->refresh();
        $this->assertSame(3000.0, (float) $factura->paid_amount);
        $this->assertSame('partially_paid', $factura->status);
        $this->assertSame($factura->id, (int) Receipt::withoutGlobalScopes()->find($p->receipt_id)->purchase_invoice_id);
        $this->assertSame($factura->id, (int) Transaction::withoutGlobalScopes()->find($p->transaction_id)->purchase_id);

        // Um só movimento de tesouraria para este dinheiro.
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('category', 'supplier_payment')->count());
    }

    /** Com a factura já emitida, o pagamento vai logo para ela. */
    public function test_pago_depois_da_factura_vai_direito_a_ela(): void
    {
        $conta = $this->conta();
        $e = $this->enviada(10, 1000);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->receber($e->fresh(), $this->tenant->id, $this->user->id, [$e->itens()->first()->id => 10]);
        $factura = $fluxo->facturar($e->fresh(), $this->tenant->id, $this->user->id);
        $factura->update(['status' => 'pending']);

        $total = (float) $factura->fresh()->total;
        $p = app(FluxoDoPagamento::class)->pedir($e->fresh(), $this->tenant->id, $this->user->id, ['valor' => $total]);
        app(FluxoDoPagamento::class)->pagar($p, $this->tenant->id, $this->pessoa('treasury.pagamentos.pagar'), ['forma' => 'transfer', 'account_id' => $conta->id]);

        $this->assertSame($factura->id, (int) Receipt::withoutGlobalScopes()->find($p->fresh()->receipt_id)->purchase_invoice_id);
        $this->assertSame('paid', $factura->fresh()->status);
    }

    public function test_com_aprovacao_de_pagamento_a_tesouraria_so_paga_depois_do_sim(): void
    {
        $this->regras(pagamento: 1);
        $e = $this->enviada();
        $pagamentos = app(FluxoDoPagamento::class);
        $p = $pagamentos->pedir($e, $this->tenant->id, $this->user->id, ['valor' => 1000]);
        $this->assertSame('em_aprovacao', $p->estado);

        $tesoureiro = $this->pessoa('treasury.pagamentos.pagar');

        try {
            $pagamentos->pagar($p, $this->tenant->id, $tesoureiro, ['forma' => 'cash']);
            $this->fail('não se paga o que não foi aprovado');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('à espera de aprovação', $ex->getMessage());
        }

        $pagamentos->decidir($p->fresh(), $this->tenant->id, $this->pessoa('compras.pagamentos.aprovar'), true, null);
        $this->assertSame('por_pagar', $p->fresh()->estado);
    }

    public function test_cancelar_a_encomenda_cancela_os_pedidos_mas_nao_o_dinheiro_ja_pago(): void
    {
        $e = $this->enviada();
        $pagamentos = app(FluxoDoPagamento::class);
        $pendente = $pagamentos->pedir($e, $this->tenant->id, $this->user->id, ['valor' => 1000]);

        app(FluxoDaEncomenda::class)->cancelar($e->fresh(), $this->tenant->id);
        $this->assertSame('cancelado', $pendente->fresh()->estado);

        $outra = $this->enviada();
        $pago = $pagamentos->pedir($outra, $this->tenant->id, $this->user->id, ['valor' => 1000]);
        $pagamentos->pagar($pago, $this->tenant->id, $this->pessoa('treasury.pagamentos.pagar'), ['forma' => 'transfer', 'account_id' => $this->conta()->id]);

        $this->expectExceptionMessage('Trate o reembolso');
        app(FluxoDaEncomenda::class)->cancelar($outra->fresh(), $this->tenant->id);
    }

    // ─── Pelas portas (permissões) ───────────────────────────────────────

    public function test_pedir_e_pagar_sao_permissoes_diferentes(): void
    {
        $e = $this->enviada();
        $comprador = $this->pessoa(...self::PEDIR);

        $this->actingAs($comprador)
            ->postJson("/api/v1/invoicing/react/compras/encomendas/{$e->id}/pagamentos", ['valor' => 500, 'data_limite' => today()->addDays(3)->toDateString()])
            ->assertCreated();

        $p = PedidoDePagamento::where('encomenda_id', $e->id)->sole();
        $conta = $this->conta();

        // Quem compra não paga.
        $this->actingAs($comprador)
            ->postJson("/api/v1/invoicing/react/tesouraria/pagamentos/{$p->id}/pagar", ['forma' => 'transfer', 'account_id' => $conta->id])
            ->assertForbidden();

        $tesoureiro = $this->pessoa('treasury.pagamentos.view', 'treasury.pagamentos.pagar');

        $this->actingAs($tesoureiro)
            ->getJson('/api/v1/invoicing/react/tesouraria/pagamentos')
            ->assertOk()
            ->assertJsonPath('data.0.numero', $p->numero)
            ->assertJsonPath('resumo.por_pagar', 1);

        $this->actingAs($tesoureiro)
            ->postJson("/api/v1/invoicing/react/tesouraria/pagamentos/{$p->id}/pagar", ['forma' => 'transfer', 'account_id' => $conta->id])
            ->assertOk();

        $this->assertSame('pago', $p->fresh()->estado);
    }

    /** A ficha conta a história: quem pediu, quem aprovou, quem pagou. */
    public function test_a_ficha_da_encomenda_mostra_o_rasto_com_quem_fez_cada_passo(): void
    {
        $e = $this->enviada();
        $p = app(FluxoDoPagamento::class)->pedir($e, $this->tenant->id, $this->user->id, ['valor' => 500]);
        $tesoureiro = $this->pessoa('treasury.pagamentos.pagar');
        app(FluxoDoPagamento::class)->pagar($p, $this->tenant->id, $tesoureiro, ['forma' => 'transfer', 'account_id' => $this->conta()->id]);

        $this->comPermissoes('compras.encomendas.view');
        $r = $this->getJson("/api/v1/invoicing/react/compras/encomendas/{$e->id}")->assertOk();

        $passos = collect($r->json('rasto'));
        $this->assertTrue($passos->contains(fn ($x) => str_contains($x['passo'], 'Criou a encomenda') && $x['quem'] === $this->user->name));
        $this->assertTrue($passos->contains(fn ($x) => str_contains($x['passo'], 'Pediu o pagamento')));
        $this->assertTrue($passos->contains(fn ($x) => str_contains($x['passo'], 'Pagou') && $x['quem'] === $tesoureiro->name));
        $this->assertSame('pago', $r->json('pagamentos.0.estado'));
        $this->assertSame(500.0, (float) $r->json('data.pagamento.pago'));
    }

    // ─── «Registar como paga» na factura de compra ───────────────────────

    private function registarCompra(array $extra = []): \Illuminate\Testing\TestResponse
    {
        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'F', 'type' => 'pessoa_juridica', 'is_active' => true]);
        $produto = $this->produtoComStock(0);

        return $this->postJson('/api/v1/invoicing/react/compra', array_merge([
            'supplier_id' => $fornecedor->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => today()->toDateString(),
            'status' => 'paid',
            'linhas' => [['product_id' => $produto->id, 'quantity' => 2, 'price' => 5000]],
        ], $extra));
    }

    public function test_registar_como_paga_exige_quem_pode_pagar_e_faz_sair_o_dinheiro(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');
        $conta = $this->conta(50_000);

        $this->registarCompra(['pagamento' => ['forma' => 'transfer', 'account_id' => $conta->id]])
            ->assertForbidden();

        $this->comPermissoes('treasury.pagamentos.pagar');

        $r = $this->registarCompra(['pagamento' => ['forma' => 'transfer', 'account_id' => $conta->id]])->assertCreated();

        $factura = PurchaseInvoice::withoutGlobalScopes()->findOrFail($r->json('id'));
        $this->assertSame('paid', $factura->status);
        $this->assertSame((float) $factura->total, (float) $factura->paid_amount);

        $recibo = Receipt::withoutGlobalScopes()->where('purchase_invoice_id', $factura->id)->sole();
        $this->assertTrue(Transaction::withoutGlobalScopes()->where('related_type', Receipt::class)->where('related_id', $recibo->id)->where('type', 'expense')->exists());
        $this->assertSame(round(50_000 - (float) $factura->total, 2), (float) $conta->fresh()->current_balance);
    }

    // ─── As regras ───────────────────────────────────────────────────────

    public function test_nao_se_exigem_mais_aprovacoes_do_que_pessoas_que_podem_aprovar(): void
    {
        $this->comPermissoes('compras.view', 'compras.definicoes.manage');
        $this->pessoa('compras.encomendas.aprovar');

        $this->putJson('/api/v1/invoicing/react/compras/definicoes', ['aprovacoes_encomenda' => 2, 'aprovacoes_pagamento' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('aprovacoes_encomenda');

        $this->putJson('/api/v1/invoicing/react/compras/definicoes', ['aprovacoes_encomenda' => 1, 'aprovacoes_pagamento' => 0])
            ->assertOk();

        $this->assertSame(1, DefinicoesDasCompras::da($this->tenant->id)->aprovacoes_encomenda);
    }

    /** O sino avisa cada um do passo que é seu. */
    public function test_o_sino_avisa_o_tesoureiro_do_que_tem_para_pagar(): void
    {
        $e = $this->enviada();
        app(FluxoDoPagamento::class)->pedir($e, $this->tenant->id, $this->user->id, ['valor' => 800]);

        $tesoureiro = $this->pessoa('treasury.pagamentos.pagar');
        $avisos = app(\App\Services\Casca\NotificacoesDoSistema::class)->para($tesoureiro);

        $this->assertTrue(collect($avisos)->contains(fn ($a) => $a['titulo'] === 'Pagamentos a fornecedores'));
    }
}

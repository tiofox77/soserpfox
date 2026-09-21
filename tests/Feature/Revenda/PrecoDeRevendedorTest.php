<?php

namespace Tests\Feature\Revenda;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Services\Revenda\ComissoesDoRevendedor;
use App\Services\Revenda\EmpresasDoRevendedor;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Services\Revenda\RegraDeComissao;
use Tests\TenantTestCase;

/**
 * O PREÇO DE REVENDEDOR — o desconto substitui a comissão (21/09/2026).
 *
 * Decisão do utilizador: quando é o REVENDEDOR a pagar pelo cliente, paga o
 * preço de tabela menos a comissão que ganharia — e não nasce comissão por
 * pagar sobre esse pagamento, porque já a recebeu à cabeça. Quando é o cliente
 * a pagar, tudo como antes: preço de tabela e comissão depois.
 *
 * O que estes ensaios guardam é que ele ganha O MESMO nos dois caminhos, e
 * NUNCA duas vezes pelo mesmo pagamento.
 */
class PrecoDeRevendedorTest extends TenantTestCase
{
    private Plan $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plano = Plan::create([
            'name' => 'Revenda ' . uniqid(), 'slug' => 'revenda-' . uniqid(), 'description' => 'x',
            'price_monthly' => 25000, 'price_yearly' => 250000, 'trial_days' => 0,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true, 'is_public' => true, 'order' => 9,
        ]);
    }

    private function revendedor(array $regra = []): Reseller
    {
        $r = Reseller::create(['name' => 'João Revende', 'email' => 'rev' . uniqid() . '@exemplo.ao', 'password' => 'Senha-forte-1']);
        $r->forceFill(['status' => 'aprovado', 'code' => Reseller::novoCodigo('João'), 'commission' => RegraDeComissao::de($regra)->paraGuardar()])->save();

        return $r;
    }

    private function ligar(Reseller $r): void
    {
        $this->assertTrue(LigacaoAoRevendedor::ligar($this->tenant, $r, 'link'));
        $this->tenant->forceFill(['reseller_linked_at' => now()->subDay()])->save();
    }

    private function dono(): \App\Models\User
    {
        return \App\Models\User::create(['name' => 'Dono', 'email' => 'dono' . uniqid() . '@exemplo.ao', 'password' => 'x', 'is_super_admin' => true, 'is_active' => true]);
    }

    private function aprovar(Order $o): Order
    {
        $quem = $this->dono();
        auth()->setUser($quem);
        $o->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $quem->id]);
        auth()->setUser($this->user);

        return $o->fresh();
    }

    private function comissaoDe(string $origem, int $id): ?ResellerCommission
    {
        return ResellerCommission::where('origin_type', $origem)->where('origin_id', $id)->first();
    }

    /* ─── O catálogo ─────────────────────────────────────────────────── */

    public function test_o_catalogo_mostra_o_preco_de_tabela_e_o_do_revendedor(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20]);

        $plano = collect(EmpresasDoRevendedor::planos($r))->firstWhere('id', $this->plano->id);

        $this->assertEqualsWithDelta(25000, $plano['precos']['monthly'], 0.01, 'o cliente paga a tabela');
        $this->assertEqualsWithDelta(20000, $plano['precos_revendedor']['monthly'], 0.01, 'o revendedor paga menos 20%');
        $this->assertEqualsWithDelta(200000, $plano['precos_revendedor']['yearly'], 0.01);
    }

    public function test_uma_excepcao_por_plano_vale_tambem_no_preco(): void
    {
        $r = $this->revendedor([
            'tipo' => 'percentagem', 'valor' => 20,
            'planos' => [['plan_id' => $this->plano->id, 'tipo' => 'fixo', 'valor' => 3000]],
        ]);

        $plano = collect(EmpresasDoRevendedor::planos($r))->firstWhere('id', $this->plano->id);

        $this->assertEqualsWithDelta(22000, $plano['precos_revendedor']['monthly'], 0.01,
            'a mesma regra da comissão: aqui, 3.000 fixos para este plano');
    }

    /* ─── O pedido feito pelo revendedor ─────────────────────────────── */

    public function test_o_pedido_do_revendedor_cobra_o_preco_de_revendedor(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20]);
        $this->ligar($r);

        $pedido = app(EmpresasDoRevendedor::class)->pedirPlano($r, $this->tenant->id, [
            'plan_id' => $this->plano->id, 'ciclo' => 'monthly',
        ], null);

        $this->assertEqualsWithDelta(20000, (float) $pedido->amount, 0.01, 'transfere o preço de revendedor');
        $this->assertSame($r->id, (int) $pedido->reseller_id);
    }

    /**
     * O PERIGO A SÉRIO: ganhar duas vezes. O pedido já vem com o desconto;
     * nascer por cima uma comissão por pagar era pagar a comissão outra vez.
     */
    public function test_aprovar_o_pedido_do_revendedor_nao_da_comissao_por_pagar(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20]);
        $this->ligar($r);

        $pedido = app(EmpresasDoRevendedor::class)->pedirPlano($r, $this->tenant->id, [
            'plan_id' => $this->plano->id, 'ciclo' => 'monthly',
        ], null);

        $this->aprovar($pedido);

        $c = $this->comissaoDe('order', $pedido->id);

        $this->assertNotNull($c, 'o desconto fica registado — as contas da plataforma têm de bater');
        $this->assertSame(ComissoesDoRevendedor::COMPENSADA, $c->status, 'compensada, não por pagar');
        $this->assertEqualsWithDelta(5000, (float) $c->amount, 0.01, 'o desconto que teve');
        $this->assertEqualsWithDelta(25000, (float) $c->base_amount, 0.01);
        $this->assertEqualsWithDelta(0, (float) ComissoesDoRevendedor::totais($r->id)['por_pagar'], 0.01,
            'nada por pagar a este revendedor');
    }

    public function test_o_cliente_a_pagar_continua_a_dar_comissao_por_pagar(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20]);
        $this->ligar($r);

        // O cliente pediu ele próprio, ao preço de tabela.
        $pedido = Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'plan_id' => $this->plano->id,
            'amount' => 25000, 'billing_cycle' => 'monthly', 'status' => 'pending', 'payment_method' => 'bank_transfer',
        ]);

        $this->aprovar($pedido);

        $c = $this->comissaoDe('order', $pedido->id);

        $this->assertNotNull($c);
        $this->assertSame('por_pagar', $c->status);
        $this->assertEqualsWithDelta(5000, (float) $c->amount, 0.01, 'ganha o mesmo, só que depois');
    }

    /* ─── A renovação paga pelo revendedor ───────────────────────────── */

    private function renovacao(float $subtotal, float $iva): Invoice
    {
        $sub = $this->tenant->subscriptions()->create([
            'plan_id' => $this->plano->id, 'status' => 'active', 'amount' => $subtotal, 'billing_cycle' => 'monthly',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(), 'ends_at' => now()->addMonth(),
        ]);

        return Invoice::create([
            'tenant_id' => $this->tenant->id, 'subscription_id' => $sub->id, 'invoice_date' => now(), 'due_date' => now(),
            'subtotal' => $subtotal, 'tax' => $iva, 'total' => $subtotal + $iva, 'status' => 'pending',
        ]);
    }

    /**
     * A regra «sobre o valor SEM IVA» tem de dar o desconto sobre o valor sem
     * IVA — passar o total como as duas bases dava-lhe mais do que a comissão.
     */
    public function test_a_renovacao_desconta_pela_base_da_regra(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20, 'base' => 'sem_iva']);
        $this->ligar($r);

        $f = $this->renovacao(25000, 3500);

        $conta = app(ComissoesDoRevendedor::class)->aPagarPeloRevendedor($r, $f);

        $this->assertEqualsWithDelta(28500, $conta['tabela'], 0.01, 'a factura fica ao preço de tabela');
        $this->assertEqualsWithDelta(5000, $conta['desconto'], 0.01, '20% de 25.000, e não de 28.500');
        $this->assertEqualsWithDelta(23500, $conta['preco'], 0.01);
    }

    public function test_a_renovacao_paga_pelo_revendedor_fica_compensada(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20, 'base' => 'sem_iva']);
        $this->ligar($r);

        $f = $this->renovacao(25000, 3500);
        $f->forceFill(['payment_submitted_at' => now(), 'payment_submitted_by_reseller_id' => $r->id])->save();
        $f->update(['status' => 'paid', 'paid_at' => now()]);

        $c = $this->comissaoDe('invoice', $f->id);

        $this->assertNotNull($c);
        $this->assertSame(ComissoesDoRevendedor::COMPENSADA, $c->status);
        $this->assertEqualsWithDelta(5000, (float) $c->amount, 0.01);
        $this->assertEqualsWithDelta(28500, (float) $f->fresh()->total, 0.01,
            'a factura é documento fiscal — o total não se mexe');
    }

    public function test_os_totais_do_revendedor_separam_o_descontado(): void
    {
        $r = $this->revendedor(['tipo' => 'percentagem', 'valor' => 20]);
        $this->ligar($r);

        $pedido = app(EmpresasDoRevendedor::class)->pedirPlano($r, $this->tenant->id, [
            'plan_id' => $this->plano->id, 'ciclo' => 'monthly',
        ], null);
        $this->aprovar($pedido);

        $totais = ComissoesDoRevendedor::totais($r->id);

        $this->assertEqualsWithDelta(5000, $totais['descontado'], 0.01);
        $this->assertEqualsWithDelta(0, $totais['por_pagar'], 0.01);
        $this->assertEqualsWithDelta(0, $totais['pago'], 0.01);
    }
}

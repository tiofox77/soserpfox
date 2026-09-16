<?php

namespace Tests\Feature\Revenda;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Services\Revenda\ComissoesDoRevendedor;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Services\Revenda\RegraDeComissao;
use Tests\TenantTestCase;

/**
 * AS COMISSÕES DOS REVENDEDORES (16/09/2026, RV-01, RV-04 e RV-11).
 */
class ComissoesDoRevendedorTest extends TenantTestCase
{
    private Plan $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plano = Plan::create([
            'name' => 'Revenda ' . uniqid(), 'slug' => 'revenda-' . uniqid(), 'description' => 'x',
            'price_monthly' => 10000, 'price_yearly' => 100000, 'trial_days' => 0,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true, 'order' => 9,
        ]);
    }

    private function revendedor(array $regra = [], string $estado = 'aprovado'): Reseller
    {
        $r = Reseller::create(['name' => 'João Revende', 'email' => 'rev' . uniqid() . '@exemplo.ao', 'password' => 'Senha-forte-1']);
        $r->forceFill(['status' => $estado, 'code' => Reseller::novoCodigo('João'), 'commission' => RegraDeComissao::de($regra)->paraGuardar()])->save();

        return $r;
    }

    private function ligar(Reseller $r, $quando = null): void
    {
        $this->assertTrue(LigacaoAoRevendedor::ligar($this->tenant, $r, 'link'));
        if ($quando) {
            $this->tenant->forceFill(['reseller_linked_at' => $quando])->save();
        }
    }

    /** O dono da plataforma — é a aprovação dele que confirma um pagamento. */
    private function dono(): \App\Models\User
    {
        return \App\Models\User::create(['name' => 'Dono da plataforma', 'email' => 'dono' . uniqid() . '@exemplo.ao', 'password' => 'x', 'is_super_admin' => true, 'is_active' => true]);
    }

    private function pedidoAprovado(float $valor = 10000, ?\App\Models\User $quem = null, $quando = null): Order
    {
        $o = Order::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'plan_id' => $this->plano->id, 'amount' => $valor, 'billing_cycle' => 'monthly', 'status' => 'pending', 'payment_method' => 'bank_transfer']);
        $quem ??= $this->dono();
        auth()->setUser($quem);
        $o->update(['status' => 'approved', 'approved_at' => $quando ?? now(), 'approved_by' => $quem->id]);
        auth()->setUser($this->user);

        return $o;
    }

    private function facturaPaga(float $subtotal, float $iva): Invoice
    {
        $sub = $this->tenant->subscriptions()->create(['plan_id' => $this->plano->id, 'status' => 'active', 'amount' => $subtotal, 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(), 'ends_at' => now()->addMonth()]);
        $f = Invoice::create(['tenant_id' => $this->tenant->id, 'subscription_id' => $sub->id, 'invoice_date' => now(), 'due_date' => now(), 'subtotal' => $subtotal, 'tax' => $iva, 'total' => $subtotal + $iva, 'status' => 'pending']);
        $f->update(['status' => 'paid', 'paid_at' => now()]);

        return $f;
    }

    public function test_a_regra_calcula_percentagem_fixo_e_excepcao_por_plano(): void
    {
        $regra = RegraDeComissao::de(['tipo' => 'percentagem', 'valor' => 15, 'base' => 'sem_iva', 'planos' => [['plan_id' => 7, 'tipo' => 'fixo', 'valor' => 2500]]]);

        $this->assertSame(['base' => 35018.0, 'valor' => 5252.7, 'tipo' => 'percentagem', 'taxa' => 15.0], $regra->calcular(35018, 39920.52, 3));
        $this->assertSame(2500.0, $regra->calcular(35018, 39920.52, 7)['valor']);
        $this->assertSame(39920.52, RegraDeComissao::de(['base' => 'com_iva'])->calcular(35018, 39920.52, null)['base']);
        $this->assertStringContainsString('15%', $regra->resumo());

        $this->assertTrue(RegraDeComissao::de(['aplica' => 'primeiro'])->aplicaSe(false, 0));
        $this->assertFalse(RegraDeComissao::de(['aplica' => 'primeiro'])->aplicaSe(true, 0));
        $this->assertTrue(RegraDeComissao::de(['aplica' => 'meses', 'meses' => 12])->aplicaSe(true, 11));
        $this->assertFalse(RegraDeComissao::de(['aplica' => 'meses', 'meses' => 12])->aplicaSe(true, 12));
    }

    public function test_o_pedido_aprovado_por_uma_pessoa_da_comissao_e_so_uma(): void
    {
        $r = $this->revendedor(['valor' => 20]);
        $this->ligar($r);

        $pedido = $this->pedidoAprovado(12000);
        $c = ResellerCommission::where('origin_type', 'order')->where('origin_id', $pedido->id)->firstOrFail();
        $this->assertSame([$r->id, 2400.0, 'por_pagar'], [$c->reseller_id, (float) $c->amount, $c->status]);

        // Voltar a gravar o mesmo pagamento não dá outra.
        $pedido->update(['status' => 'pending']);
        $pedido->update(['status' => 'approved']);
        $this->assertSame(1, ResellerCommission::where('origin_id', $pedido->id)->count());

        // O teste gratuito aprova-se sozinho, do lado da empresa: não é dinheiro.
        $auto = $this->pedidoAprovado(10000, $this->user);
        $this->assertFalse(ResellerCommission::where("origin_type", "order")->where("origin_id", $auto->id)->exists());
    }

    public function test_a_factura_paga_da_renovacao_da_comissao_sobre_a_base_escolhida(): void
    {
        $this->ligar($this->revendedor(['valor' => 10, 'base' => 'com_iva']));

        $f = $this->facturaPaga(10000, 1400);

        $c = ResellerCommission::where('origin_type', 'invoice')->where('origin_id', $f->id)->firstOrFail();
        $this->assertSame([11400.0, 1140.0], [(float) $c->base_amount, (float) $c->amount]);
    }

    public function test_so_no_primeiro_pagamento_e_nos_primeiros_meses(): void
    {
        $this->ligar($this->revendedor(['aplica' => 'primeiro']));
        $this->pedidoAprovado();
        $this->pedidoAprovado();
        $this->assertSame(1, ResellerCommission::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_depois_dos_meses_da_regra_ja_nao_ha_comissao(): void
    {
        $this->ligar($this->revendedor(['aplica' => 'meses', 'meses' => 3]), now()->subMonths(4));
        $this->pedidoAprovado();
        $this->assertSame(0, ResellerCommission::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_sem_revendedor_aprovado_ou_antes_da_ligacao_nao_ha_comissao(): void
    {
        // Sem revendedor.
        $this->pedidoAprovado();
        $this->assertSame(0, ResellerCommission::count());

        // Suspenso: a ligação fica, a comissão não nasce.
        $r = $this->revendedor();
        $this->ligar($r);
        $r->forceFill(['status' => 'suspenso'])->save();
        $this->pedidoAprovado();
        $this->assertSame(0, ResellerCommission::where('tenant_id', $this->tenant->id)->count());

        // Um pagamento com data de antes da ligação.
        $r->forceFill(['status' => 'aprovado'])->save();
        $velho = $this->pedidoAprovado(10000, null, now()->subYear());
        $this->assertFalse(ResellerCommission::where('origin_id', $velho->id)->exists());
    }

    public function test_a_primeira_ligacao_fica(): void
    {
        $primeiro = $this->revendedor();
        $segundo = $this->revendedor();

        $this->assertTrue(LigacaoAoRevendedor::ligar($this->tenant, $primeiro, 'codigo'));
        $this->assertFalse(LigacaoAoRevendedor::ligar($this->tenant->fresh(), $segundo, 'link'));
        $this->assertSame([$primeiro->id, 'codigo'], [$this->tenant->fresh()->reseller_id, $this->tenant->fresh()->reseller_via]);

        // Só um aprovado se encontra pelo código.
        $pendente = $this->revendedor([], 'pendente');
        $this->assertNull(LigacaoAoRevendedor::porCodigo($pendente->code));
        $this->assertSame($primeiro->id, LigacaoAoRevendedor::porCodigo(strtolower($primeiro->code))?->id);
    }

    public function test_pagar_soma_as_escolhidas_e_anular_so_as_por_pagar(): void
    {
        $r = $this->revendedor(['valor' => 10]);
        $this->ligar($r);
        $a = ResellerCommission::where('origin_id', $this->pedidoAprovado(10000)->id)->first();
        $b = ResellerCommission::where('origin_id', $this->pedidoAprovado(20000)->id)->first();
        $c = ResellerCommission::where('origin_id', $this->pedidoAprovado(30000)->id)->first();

        $servico = app(ComissoesDoRevendedor::class);
        $pagamento = $servico->pagar($r, [$a->id, $b->id], ['method' => 'transferencia', 'reference' => 'TRF-1', 'paid_at' => now()->toDateString()], $this->user->id);

        $this->assertSame(3000.0, (float) $pagamento->amount);
        $this->assertSame(['paga', 'paga', 'por_pagar'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
        $this->assertSame(['por_pagar' => 3000.0, 'por_pagar_n' => 1, 'pago' => 3000.0, 'anulado' => 0.0], array_intersect_key(ComissoesDoRevendedor::totais($r->id), array_flip(['por_pagar', 'por_pagar_n', 'pago', 'anulado'])));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $servico->anular($c, 'Pedido devolvido', $this->user->id);
            $this->assertSame('anulada', $c->fresh()->status);
            $servico->anular($a->fresh(), 'x', $this->user->id);
        } finally {
            // Pagar duas vezes a mesma também não passa.
            $this->assertSame(1, \App\Models\ResellerPayout::where('reseller_id', $r->id)->count());
        }
    }
}

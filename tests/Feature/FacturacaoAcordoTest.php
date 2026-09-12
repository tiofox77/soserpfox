<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * Gestão de Facturação («admin a pagar o plano»): o acordo também entra aqui.
 *
 * O ecrã de facturação do dono da plataforma é a outra porta por onde se fecha
 * um plano à mão — e dava sempre 14 meses ao anual e o preço de tabela. Agora
 * grava o mesmo acordo que a janela «Alterar o plano»: oferta opcional, dias à
 * medida, preço por utilizador — pela MESMA regra (AcordoDeSubscricao).
 *
 * O ecrã passou a React: fala com `/api/v1/plataforma/react/facturacao`.
 */
class FacturacaoAcordoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O ecrã só abre ao dono da plataforma.
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user->fresh());
    }

    private function plano(float $mensal = 10000, float $anual = 100000, int $utilizadores = 10): Plan
    {
        return Plan::create([
            'name' => 'Plano '.uniqid(), 'slug' => 'plano-'.uniqid(),
            'price_monthly' => $mensal, 'price_yearly' => $anual, 'trial_days' => 0,
            'max_users' => $utilizadores, 'max_storage_mb' => 5000, 'is_active' => true,
        ]);
    }

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Empresa '.uniqid(), 'slug' => 'emp-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
    }

    private function resumo(array $escolha): array
    {
        return $this->postJson('/api/v1/plataforma/react/facturacao/subscricoes/resumo', $escolha)->assertOk()->json();
    }

    /** @test */
    public function o_resumo_segue_o_acordo(): void
    {
        $plano = $this->plano(10000, 100000, 10);
        $base = ['plan_id' => $plano->id, 'billing_cycle' => 'yearly'];

        $r = $this->resumo($base);
        $this->assertSame(100000.0, (float) $r['valor']);
        $this->assertSame(now()->addMonths(14)->format('d/m/Y'), $r['fim']);

        $r = $this->resumo($base + ['com_oferta' => false]);
        $this->assertSame(now()->addMonths(12)->format('d/m/Y'), $r['fim']);

        $r = $this->resumo($base + ['com_oferta' => false, 'preco_por_utilizador' => 1500, 'utilizadores' => 4]);
        $this->assertSame(6000.0, (float) $r['valor']);
    }

    /** @test */
    public function marcar_como_pago_grava_o_acordo_na_subscricao(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano(10000, 100000, 10);

        $this->postJson('/api/v1/plataforma/react/facturacao/subscricoes', [
            'tenant_id' => $empresa->id,
            'plan_id' => $plano->id,
            'billing_cycle' => 'yearly',
            'com_oferta' => false,
            'preco_por_utilizador' => 1500,
            'utilizadores' => 4,
            'pago' => true,
        ])->assertCreated();

        $sub = Subscription::where('tenant_id', $empresa->id)->where('status', 'active')->first();

        $this->assertNotNull($sub, 'a subscrição activa não foi criada');
        $this->assertFalse((bool) $sub->com_oferta);
        $this->assertSame(12, (int) $sub->current_period_start->diffInMonths($sub->current_period_end));
        $this->assertSame(6000.0, (float) $sub->amount);
        $this->assertSame(1500.0, (float) $sub->preco_por_utilizador);
        $this->assertSame(4, (int) $sub->utilizadores_cobrados);
    }

    /** @test */
    public function dias_a_medida_ganham_ao_ciclo_tambem_aqui(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();

        $this->postJson('/api/v1/plataforma/react/facturacao/subscricoes', [
            'tenant_id' => $empresa->id,
            'plan_id' => $plano->id,
            'billing_cycle' => 'yearly',
            'dias' => 364,
            'pago' => true,
        ])->assertCreated();

        $sub = Subscription::where('tenant_id', $empresa->id)->where('status', 'active')->first();

        $this->assertSame(364, (int) $sub->dias_personalizados);
        $this->assertSame(364, (int) $sub->current_period_start->diffInDays($sub->current_period_end));
    }

    /** Editar traz o acordo gravado — não o «esquece». */
    public function test_editar_traz_o_acordo_gravado(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();
        $sub = app(\App\Services\Plataforma\TrocarDePlano::class)->aplicar($empresa, $plano, 'yearly', [
            'com_oferta' => false, 'preco_por_utilizador' => 2000, 'utilizadores' => 3,
        ]);

        $ficha = $this->getJson("/api/v1/plataforma/react/facturacao/subscricoes/{$sub->id}")->assertOk()->json('ficha');

        $this->assertFalse($ficha['com_oferta']);
        $this->assertSame(2000.0, (float) $ficha['preco_por_utilizador']);
        $this->assertSame(3, $ficha['utilizadores']);
    }
}

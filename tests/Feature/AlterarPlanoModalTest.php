<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plataforma\TrocarDePlano;
use Tests\TenantTestCase;

/**
 * A janela «Alterar o plano» é dinâmica e grava o acordo que mostra.
 *
 * Duas queixas do dono (2026-09-02): a janela mostrava um plano actual
 * diferente do da lista, e não deixava fechar um acordo diferente da tabela
 * (sem oferta, N dias, preço por utilizador). O que se prende aqui:
 *
 *   · abrir traz o acordo ACTUAL da empresa, tal como está;
 *   · o resumo recalcula ao mudar ciclo/dias/preço, no servidor, pela
 *     mesma regra que grava;
 *   · o que se grava é exactamente o que o resumo prometeu.
 *
 * Era um ensaio do componente em Livewire; o ecrã passou a React e fala com
 * `/api/v1/plataforma/react/empresas/{id}/plano`, atrás da guarda do dono.
 */
class AlterarPlanoModalTest extends TenantTestCase
{
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

    private function dono(): static
    {
        $dono = User::create(['name' => 'Dono', 'email' => 'dono_'.uniqid().'@exemplo.ao', 'password' => bcrypt('x')]);
        $dono->forceFill(['is_super_admin' => true])->save();

        return $this->actingAs($dono);
    }

    private function resumo(Tenant $empresa, array $escolha): array
    {
        return $this->dono()
            ->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano/resumo", $escolha)
            ->assertOk()->json();
    }

    /** @test */
    public function abrir_o_modal_traz_o_acordo_actual_da_empresa(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();
        app(TrocarDePlano::class)->aplicar($empresa, $plano, 'yearly', [
            'com_oferta' => false, 'preco_por_utilizador' => 1500, 'utilizadores' => 4,
        ]);

        $actual = $this->dono()
            ->getJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano")
            ->assertOk()->json('actual');

        $this->assertSame($plano->id, $actual['plano_id']);
        $this->assertSame('yearly', $actual['ciclo']);
        $this->assertFalse($actual['com_oferta']);
        $this->assertSame(1500.0, (float) $actual['preco_por_utilizador']);
        $this->assertSame(4, $actual['utilizadores_cobrados']);
    }

    /** @test */
    public function o_resumo_recalcula_com_o_que_se_escreve(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano(10000, 100000, 10);
        $base = ['plano' => $plano->id, 'ciclo' => 'yearly'];

        // Anual com oferta: 14 meses, preço de tabela.
        $r = $this->resumo($empresa, $base);
        $this->assertSame(100000.0, (float) $r['valor']);
        $this->assertSame(now()->addMonths(14)->format('d/m/Y'), $r['fim']);
        $this->assertTrue($r['oferta_aplicavel']);

        // Tira-se a oferta: 12 meses.
        $r = $this->resumo($empresa, $base + ['com_oferta' => false]);
        $this->assertSame(now()->addMonths(12)->format('d/m/Y'), $r['fim']);

        // Dias à medida ganham ao ciclo.
        $r = $this->resumo($empresa, $base + ['com_oferta' => false, 'dias' => 364]);
        $this->assertSame(364, $r['dias']);
        $this->assertFalse($r['oferta_aplicavel'], 'com dias à medida a oferta não se aplica');

        // Preço por utilizador: N × preço, N por omissão = os do plano.
        $r = $this->resumo($empresa, $base + ['dias' => 364, 'preco_por_utilizador' => 2000]);
        $this->assertSame(20000.0, (float) $r['valor']);
        $r = $this->resumo($empresa, $base + ['dias' => 364, 'preco_por_utilizador' => 2000, 'utilizadores' => 3]);
        $this->assertSame(6000.0, (float) $r['valor']);
    }

    /** @test */
    public function grava_exactamente_o_que_o_resumo_prometeu(): void
    {
        $empresa = $this->empresa();
        $antigo = $this->plano();
        $novo = $this->plano(12000, 120000, 20);
        app(TrocarDePlano::class)->aplicar($empresa, $antigo, 'monthly');

        $escolha = [
            'plano' => $novo->id, 'ciclo' => 'yearly', 'com_oferta' => false,
            'preco_por_utilizador' => 1500, 'utilizadores' => 6,
        ];

        $prometido = $this->resumo($empresa, $escolha);

        $this->dono()->putJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano", $escolha)->assertOk();

        $sub = $empresa->fresh()->activeSubscription;

        $this->assertSame($novo->id, $sub->plan_id);
        $this->assertSame('yearly', $sub->billing_cycle);
        $this->assertFalse((bool) $sub->com_oferta);
        $this->assertSame(12, (int) $sub->current_period_start->diffInMonths($sub->current_period_end));
        $this->assertSame(9000.0, (float) $sub->amount);
        $this->assertSame((float) $prometido['valor'], (float) $sub->amount, 'grava o que o resumo prometeu');
        $this->assertSame(6, (int) $sub->utilizadores_cobrados);
    }

    /** @test */
    public function dias_a_medida_ficam_gravados(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();

        $this->dono()->putJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano", [
            'plano' => $plano->id, 'ciclo' => 'yearly', 'dias' => 364,
        ])->assertOk();

        $sub = $empresa->fresh()->activeSubscription;

        $this->assertSame(364, (int) $sub->dias_personalizados);
        $this->assertSame(364, (int) $sub->current_period_start->diffInDays($sub->current_period_end));
    }

    /** @test */
    public function dias_absurdos_nao_passam_a_validacao(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();

        $this->dono()->putJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano", [
            'plano' => $plano->id, 'ciclo' => 'monthly', 'dias' => 99999,
        ])->assertStatus(422)->assertJsonValidationErrors('dias');
    }

    /**
     * A lista e a janela têm de concordar. Uma empresa com DUAS subscrições
     * vivas (herança de antes do TrocarDePlano cancelar tudo) mostrava uma
     * na lista e outra na janela — a relação não tinha ordem determinística.
     *
     * @test
     */
    public function com_duas_subscricoes_vivas_ganha_a_que_acaba_mais_tarde(): void
    {
        $empresa = $this->empresa();
        $curto = $this->plano();
        $longo = $this->plano();

        $empresa->subscriptions()->create([
            'plan_id' => $curto->id, 'billing_cycle' => 'monthly', 'amount' => 1, 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $empresa->subscriptions()->create([
            'plan_id' => $longo->id, 'billing_cycle' => 'yearly', 'amount' => 1, 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        // Dez leituras, sempre a mesma resposta.
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($longo->id, Tenant::with('activeSubscription')->find($empresa->id)->activeSubscription->plan_id);
        }
    }
}

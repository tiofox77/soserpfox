<?php

namespace Tests\Feature;

use App\Models\Plan;
use Tests\TenantTestCase;

/**
 * Afinar os dias de teste de planos concretos — e só desses.
 */
class DiasDeTesteDosPlanosTest extends TenantTestCase
{
    private function plano(string $slug, int $dias, bool $auto = true): Plan
    {
        return Plan::create([
            'name'          => ucfirst($slug),
            'slug'          => $slug,
            'price_monthly' => 1000,
            'trial_days'    => $dias,
            'auto_activate' => $auto,
            'is_active'     => true,
        ]);
    }

    public function test_muda_apenas_os_planos_indicados(): void
    {
        $alvo  = $this->plano('alvo-' . uniqid(), 30);
        $outro = $this->plano('outro-' . uniqid(), 30);

        $this->artisan('planos:dias-de-teste', [
            '--planos' => $alvo->slug,
            '--dias'   => 14,
        ])->assertSuccessful();

        $this->assertSame(14, (int) $alvo->fresh()->trial_days);
        $this->assertSame(30, (int) $outro->fresh()->trial_days,
            'um plano não indicado não pode ser tocado');
    }

    public function test_nao_mexe_sem_planos_indicados(): void
    {
        $this->artisan('planos:dias-de-teste', ['--dias' => 14])->assertFailed();
    }

    public function test_recusa_dias_invalidos(): void
    {
        $p = $this->plano('inv-' . uniqid(), 30);

        $this->artisan('planos:dias-de-teste', [
            '--planos' => $p->slug,
            '--dias'   => 'abc',
        ])->assertFailed();

        $this->assertSame(30, (int) $p->fresh()->trial_days);
    }

    public function test_so_ver_nao_grava(): void
    {
        $p = $this->plano('ver-' . uniqid(), 30);

        $this->artisan('planos:dias-de-teste', [
            '--planos' => $p->slug,
            '--dias'   => 99,
            '--so-ver' => true,
        ])->assertSuccessful();

        $this->assertSame(30, (int) $p->fresh()->trial_days);
    }

    public function test_a_activacao_automatica_nao_e_afectada(): void
    {
        $p = $this->plano('auto-' . uniqid(), 30, true);

        $this->artisan('planos:dias-de-teste', [
            '--planos' => $p->slug,
            '--dias'   => 14,
        ])->assertSuccessful();

        $this->assertTrue((bool) $p->fresh()->auto_activate,
            'mudar os dias não pode desligar o arranque automático');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Plan;
use App\Services\Plataforma\PlanoAMedida;
use Tests\TenantTestCase;

/**
 * Montar um plano à medida de um cliente e atribuir-lho.
 */
class PlanoAMedidaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['invoicing', 'treasury', 'rh', 'salon'] as $slug) {
            Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_core' => false]);
        }
    }

    private function montar(array $extra = []): Plan
    {
        return app(PlanoAMedida::class)->criarEAtribuir($this->tenant, array_merge([
            'nome'           => 'Plano Farmácia X',
            'modulos'        => ['invoicing', 'rh'],
            'preco_mensal'   => 25000,
            'max_users'      => 12,
            'max_companies'  => 2,
            'max_storage_mb' => 8000,
            'ciclo'          => 'monthly',
        ], $extra));
    }

    public function test_cria_o_plano_com_os_modulos_escolhidos(): void
    {
        $plano = $this->montar();

        $slugs = $plano->modules()->pluck('modules.slug')->all();

        $this->assertContains('invoicing', $slugs);
        $this->assertContains('rh', $slugs);
        $this->assertSame(25000.0, (float) $plano->price_monthly);
    }

    public function test_o_plano_fica_fora_da_montra_mas_activo(): void
    {
        $plano = $this->montar();

        $this->assertTrue((bool) $plano->is_active, 'tem de funcionar na subscrição');
        $this->assertFalse((bool) $plano->is_public, 'não pertence à montra');

        // E, por isso, não aparece na listagem pública.
        $this->assertFalse(
            Plan::publico()->pluck('id')->contains($plano->id),
            'um plano à medida não pode aparecer aos outros clientes'
        );
    }

    public function test_a_empresa_fica_com_a_subscricao_do_plano(): void
    {
        $plano = $this->montar();

        $sub = $this->tenant->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->latest('id')->first();

        $this->assertNotNull($sub);
        $this->assertSame($plano->id, $sub->plan_id);
        $this->assertNotNull($sub->current_period_end, 'é isto que dá acesso');
    }

    public function test_os_modulos_ficam_activos_na_empresa(): void
    {
        $this->montar();

        $activos = $this->tenant->modules()->wherePivot('is_active', true)
            ->pluck('modules.slug')->all();

        $this->assertContains('invoicing', $activos);
        $this->assertContains('rh', $activos);
        // Dependência resolvida: quem leva faturação leva tesouraria.
        $this->assertContains('treasury', $activos);
    }

    public function test_o_anual_em_branco_e_doze_vezes_o_mensal(): void
    {
        $plano = $this->montar(['preco_mensal' => 10000]);

        $this->assertSame(120000.0, (float) $plano->price_yearly);
    }

    public function test_o_anual_indicado_manda(): void
    {
        $plano = $this->montar(['preco_mensal' => 10000, 'preco_anual' => 100000]);

        $this->assertSame(100000.0, (float) $plano->price_yearly);
    }

    public function test_recusa_mensalidade_zero(): void
    {
        // Um plano a zero é tratado como "o plano gratuito" e queima a
        // cortesia única do cliente.
        $this->expectException(\InvalidArgumentException::class);
        $this->montar(['preco_mensal' => 0]);
    }

    public function test_recusa_sem_modulos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->montar(['modulos' => []]);
    }

    public function test_os_limites_passam_para_a_empresa(): void
    {
        $this->montar(['max_users' => 12, 'max_storage_mb' => 8000]);

        $this->assertSame(12, (int) $this->tenant->fresh()->max_users);
    }
}

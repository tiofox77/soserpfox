<?php

namespace Tests\Feature\Plataforma;

use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * AS BOAS-VINDAS DO SUPER ADMIN EM REACT (`/home` → `plataforma/inicio`).
 */
class InicioDaPlataformaTest extends TenantTestCase
{
    public function test_o_super_admin_ve_o_ecra_da_plataforma_na_pagina_inicial(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        $this->get('/home')->assertOk()->assertSee('data-ecra="plataforma/inicio"', false);
    }

    public function test_os_numeros_da_plataforma(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        $r = $this->getJson('/api/v1/plataforma/react/inicio')->assertOk()
            ->assertJsonStructure(['empresas' => ['total', 'activas', 'em_teste', 'hoje', 'semana', 'mes', 'crescimento'],
                'utilizadores', 'receita' => ['mrr', 'paga_no_mes'], 'visitas', 'empresas_recentes', 'serie', 'sistema']);

        $this->assertSame(Tenant::count(), $r->json('empresas.total'));
        $this->assertCount(14, $r->json('serie'));
        $this->assertSame(now()->toDateString(), $r->json('serie.13.dia'));
        $this->assertGreaterThanOrEqual(1, $r->json('serie.13.empresas'), 'a empresa do ensaio nasceu hoje');
    }

    public function test_quem_nao_e_super_admin_nao_entra(): void
    {
        $this->assertContains($this->getJson('/api/v1/plataforma/react/inicio')->getStatusCode(), [302, 403]);
        $this->get('/home')->assertOk()->assertSee('data-ecra="inicio"', false);
    }
}

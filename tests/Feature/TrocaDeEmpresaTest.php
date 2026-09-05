<?php

namespace Tests\Feature;

use App\Livewire\TenantSwitcher;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Trocar de empresa é um redireccionamento do servidor, não um reload no cliente.
 *
 * A versão anterior mudava a sessão numa acção Livewire e mandava o browser
 * fazer `window.location.reload()`. A página a que se voltava trazia o
 * snapshot e o token da empresa ANTERIOR: a primeira acção batia num 409/419,
 * o ecrã dizia «a sessão expirou» e recarregava, e era preciso um segundo
 * clique para ver a empresa certa.
 *
 * Um redireccionamento entrega uma página nova, com sessão e token frescos, e
 * aterra em casa — onde nada depende da empresa de onde se veio.
 */
class TrocaDeEmpresaTest extends TenantTestCase
{
    private function outraEmpresa(): Tenant
    {
        $outra = Tenant::create([
            'name' => 'Segunda Casa',
            'slug' => 'segunda-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'seg'.uniqid().'@exemplo.ao',
            'is_active' => true,
        ]);
        $this->user->tenants()->syncWithoutDetaching([$outra->id]);

        return $outra;
    }

    /** @test */
    public function trocar_muda_a_sessao_e_redirecciona_para_casa(): void
    {
        $outra = $this->outraEmpresa();

        Livewire::actingAs($this->user)->test(TenantSwitcher::class)
            ->call('switchTenant', $outra->id)
            ->assertRedirect(route('home'));

        $this->assertSame($outra->id, (int) session('active_tenant_id'),
            'a sessão tem de apontar à empresa nova antes do redireccionamento');
    }

    /**
     * A página nova já vem com a empresa certa — sem segundo clique.
     *
     * @test
     */
    public function a_pagina_seguinte_ja_mostra_a_empresa_nova(): void
    {
        $outra = $this->outraEmpresa();

        Livewire::actingAs($this->user)->test(TenantSwitcher::class)
            ->call('switchTenant', $outra->id);

        // Um componente montado de novo (= a página a que se aterra) lê a
        // empresa da sessão — e é a nova.
        Livewire::actingAs($this->user)->test(TenantSwitcher::class)
            ->assertSet('activeTenantId', $outra->id)
            ->assertSet('activeTenantName', 'Segunda Casa');
    }

    /** Sem pertencer à empresa, não há troca nem redireccionamento. */
    public function test_nao_troca_para_uma_empresa_a_que_nao_pertence(): void
    {
        $alheia = Tenant::create([
            'name' => 'Casa Alheia',
            'slug' => 'alheia-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'alheia'.uniqid().'@exemplo.ao',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)->test(TenantSwitcher::class)
            ->call('switchTenant', $alheia->id)
            ->assertNoRedirect()
            ->assertDispatched('error');

        $this->assertSame($this->tenant->id, (int) session('active_tenant_id'));
    }
}

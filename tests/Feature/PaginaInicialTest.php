<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * A PÁGINA INICIAL EM REACT (`/home` → ecrã `inicio`, dados em /api/v1/casca/inicio).
 *
 * Guarda o que se corrigiu ao passar: os números e a ficha são da empresa
 * ACTIVA (liam a empresa por omissão), os acessos rápidos levam a algum lado
 * (eram «#») e só aparecem a quem pode abrir o ecrã.
 */
class PaginaInicialTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/casca/inicio';

    public function test_a_pagina_monta_o_ecra_e_passa_o_recado_da_sessao(): void
    {
        $this->withSession(['status' => 'Tudo pronto.'])
            ->get('/home')
            ->assertOk()
            ->assertSee('data-ecra="inicio"', false)
            ->assertSee('Tudo pronto.', false);
    }

    /** O registo de uma empresa nova aterra aqui com `success` — e ninguém o via. */
    public function test_o_recado_do_registo_tambem_chega(): void
    {
        $this->withSession(['success' => 'Empresa criada com sucesso! Bem-vindo ao SOSERP.'])
            ->get('/home')
            ->assertOk()
            ->assertSee('Empresa criada com sucesso! Bem-vindo ao SOSERP.', false);
    }

    public function test_os_numeros_e_a_ficha_sao_da_empresa_activa(): void
    {
        $this->comPermissoes('customers.view');

        $segunda = Tenant::create([
            'name' => 'Segunda Empresa', 'slug' => 'segunda-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => 's' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);
        $this->user->tenants()->syncWithoutDetaching([$segunda->id => ['is_active' => true]]);
        Client::create(['tenant_id' => $segunda->id, 'name' => 'Cliente da Segunda', 'type' => 'pessoa_juridica', 'nif' => '999999999']);

        $antes = $this->getJson(self::RAIZ)->assertOk();
        $this->assertSame($this->tenant->name, $antes->json('empresa.nome'));

        session(['active_tenant_id' => $segunda->id]);
        $depois = $this->getJson(self::RAIZ)->assertOk();

        $this->assertSame('Segunda Empresa', $depois->json('empresa.nome'));
        $this->assertSame(1, $depois->json('numeros.clientes'));
    }

    public function test_os_acessos_rapidos_levam_ao_ecra_e_seguem_as_permissoes(): void
    {
        $this->assertSame([], $this->getJson(self::RAIZ)->json('acessos'));

        $this->comModulo('invoicing')->comPermissoes('invoicing.dashboard.view', 'users.view');

        $urls = array_column($this->getJson(self::RAIZ)->json('acessos'), 'url');
        $this->assertSame([route('invoicing.dashboard'), route('users.index')], $urls);
        $this->assertNotContains('#', $urls);
    }

    public function test_sem_permissao_nao_ha_numeros(): void
    {
        $this->assertSame([], $this->getJson(self::RAIZ)->json('numeros'));
    }

    public function test_o_pedido_pendente_so_aparece_a_quem_trata_do_pacote(): void
    {
        Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'plan_id' => Plan::query()->value('id'), 'amount' => 1000, 'status' => 'pending', 'payment_method' => 'transfer',
        ]);

        $this->assertNull($this->getJson(self::RAIZ)->json('avisos'));

        $this->comPermissoes('billing.manage');
        $this->assertTrue($this->getJson(self::RAIZ)->json('avisos.pedido_pendente'));
    }
}

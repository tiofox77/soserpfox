<?php

namespace Tests\Feature\Projetos;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * O papel Gestor gere mesmo — incluindo os módulos que vieram depois.
 *
 * O mapa por omissão dizia «.view, .create ou .edit» e envelheceu mal: os
 * módulos novos nomeiam a gestão com outros verbos. O Gestor abria o
 * Projetos e não podia criar um projeto, lançar horas nem facturá-las; nas
 * Compras não podia aprovar uma requisição nem dar entrada a uma encomenda.
 *
 * A regra passou a ser por EXCEPÇÃO: tudo, menos apagar, menos mandar em
 * quem manda, menos o pacote e a plataforma.
 */
class PapelGestorTest extends TenantTestCase
{
    private function mapaDoGestor(): array
    {
        return getDefaultRolePermissionMap(Permission::all())['Gestor'] ?? [];
    }

    /** @test */
    public function o_gestor_gere_os_projetos_de_ponta_a_ponta(): void
    {
        foreach (['projetos.view', 'projetos.gerir', 'projetos.tarefas.view', 'projetos.tarefas.manage',
            'projetos.horas.registar', 'projetos.horas.gerir', 'projetos.facturar'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $mapa = $this->mapaDoGestor();

        foreach (['projetos.gerir', 'projetos.tarefas.manage', 'projetos.horas.registar',
            'projetos.horas.gerir', 'projetos.facturar'] as $p) {
            $this->assertContains($p, $mapa, "o Gestor continua sem {$p}");
        }
    }

    /** @test */
    public function o_gestor_tambem_decide_nas_compras(): void
    {
        foreach (['compras.requisicoes.decidir', 'compras.encomendas.receber', 'compras.encomendas.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $mapa = $this->mapaDoGestor();

        foreach (['compras.requisicoes.decidir', 'compras.encomendas.receber', 'compras.encomendas.manage'] as $p) {
            $this->assertContains($p, $mapa);
        }
    }

    /**
     * O que fica DE FORA — e é isto que faz do Gestor um gestor e não um dono.
     *
     * @test
     */
    public function o_gestor_nao_apaga_nem_manda_em_quem_manda(): void
    {
        foreach (['invoicing.clients.delete', 'users.roles.manage', 'users.manage', 'users.permissions',
            'billing.manage', 'plans.manage', 'modules.manage', 'tenants.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $mapa = $this->mapaDoGestor();

        foreach (['invoicing.clients.delete', 'users.roles.manage', 'users.manage', 'users.permissions',
            'billing.manage', 'plans.manage', 'modules.manage', 'tenants.edit'] as $p) {
            $this->assertNotContains($p, $mapa, "o Gestor não devia poder {$p}");
        }
    }

    /** A regra é uma função só, e responde pelo nome da permissão. */
    public function test_a_regra_de_exclusao_responde_pelo_nome(): void
    {
        $this->assertTrue(naoEhParaOGestor('hotel.rooms.delete'));
        $this->assertTrue(naoEhParaOGestor('system.seja_o_que_for'));
        $this->assertTrue(naoEhParaOGestor('billing.manage'));
        $this->assertFalse(naoEhParaOGestor('projetos.facturar'));
        $this->assertFalse(naoEhParaOGestor('accounting.journals.manage'));
    }

    /** @test */
    public function completar_papeis_acrescenta_e_nunca_tira(): void
    {
        $gerir = Permission::findOrCreate('projetos.gerir', 'web');
        $inventada = Permission::findOrCreate('zzz.afinada.a.mao', 'web');

        setPermissionsTeamId($this->tenant->id);
        $gestor = Role::firstOrCreate(['name' => 'Gestor', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $gestor->syncPermissions([$inventada]);

        $this->artisan('papeis:completar', ['--tenant' => $this->tenant->id])->assertExitCode(0);
        $this->assertFalse($gestor->fresh()->hasPermissionTo('projetos.gerir'), 'a seco não escreve');

        $this->artisan('papeis:completar', ['--tenant' => $this->tenant->id, '--aplicar' => true])->assertExitCode(0);

        $novo = $gestor->fresh();
        $this->assertTrue($novo->hasPermissionTo('projetos.gerir'), 'devia ter ganho a que faltava');
        $this->assertTrue($novo->hasPermissionTo('zzz.afinada.a.mao'), 'não pode perder o que a empresa afinou à mão');
    }

    /** Correr duas vezes não duplica nada. */
    public function test_completar_papeis_e_idempotente(): void
    {
        Permission::findOrCreate('projetos.gerir', 'web');

        setPermissionsTeamId($this->tenant->id);
        Role::firstOrCreate(['name' => 'Gestor', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        $this->artisan('papeis:completar', ['--tenant' => $this->tenant->id, '--aplicar' => true])->assertExitCode(0);

        $antes = DB::table('role_has_permissions')
            ->whereIn('role_id', Role::where('tenant_id', $this->tenant->id)->pluck('id'))->count();

        $this->artisan('papeis:completar', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->expectsOutputToContain('Nada a fazer')
            ->assertExitCode(0);

        $depois = DB::table('role_has_permissions')
            ->whereIn('role_id', Role::where('tenant_id', $this->tenant->id)->pluck('id'))->count();

        $this->assertSame($antes, $depois);
    }
}

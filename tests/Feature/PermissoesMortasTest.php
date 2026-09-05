<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * Permissões que nenhum portão verifica não devem ficar no ecrã dos papéis.
 *
 * Eram 23, restos de um seeder antigo (`invoices.*`, `payments.*`,
 * `repairs.*`, `vehicles.*`, `tenants.*`). Marcá-las não fazia nada — e isso
 * é pior do que não as ter: quem gere uma empresa marca-as a pensar que está
 * a conceder alguma coisa.
 */
class PermissoesMortasTest extends TenantTestCase
{
    /** @test */
    public function apaga_a_que_ninguem_verifica_e_as_suas_atribuicoes(): void
    {
        $morta = Permission::findOrCreate('zzz_morta.qualquer', 'web');

        setPermissionsTeamId($this->tenant->id);
        $papel = Role::firstOrCreate(['name' => 'Papel '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $papel->givePermissionTo($morta);

        $this->assertSame(1, DB::table('role_has_permissions')->where('permission_id', $morta->id)->count());

        $this->artisan('permissoes:limpar-mortas')->assertExitCode(0);
        $this->assertNotNull(Permission::find($morta->id), 'a seco não apaga');

        $this->artisan('permissoes:limpar-mortas', ['--aplicar' => true])->assertExitCode(0);

        $this->assertNull(Permission::find($morta->id));
        $this->assertSame(0, DB::table('role_has_permissions')->where('permission_id', $morta->id)->count());
    }

    /**
     * NÃO APAGA o que o código verifica, nem o que pertence a um módulo.
     *
     * É a metade que interessa: um comando que apaga permissões vivas deixa
     * utilizadores sem acesso e ninguém percebe porquê.
     *
     * @test
     */
    public function nao_apaga_o_que_esta_vivo(): void
    {
        $doModulo = Permission::findOrCreate('hotel.rooms.view', 'web');       // pertence a um módulo
        $noCodigo = Permission::findOrCreate('invoicing.documents.all', 'web'); // verificada no código

        $this->artisan('permissoes:limpar-mortas', ['--aplicar' => true])->assertExitCode(0);

        $this->assertNotNull(Permission::find($doModulo->id));
        $this->assertNotNull(Permission::find($noCodigo->id));
    }

    /** As 23 do seeder antigo não voltam a aparecer no catálogo. */
    public function test_as_antigas_nao_pertencem_a_modulo_nenhum(): void
    {
        foreach (['invoices.view', 'payments.create', 'repairs.edit', 'vehicles.view', 'appointments.manage'] as $nome) {
            $this->assertNull(
                \App\Support\CatalogoDePermissoes::grupoDe($nome),
                "{$nome} não devia pertencer a módulo nenhum"
            );
        }
    }
}

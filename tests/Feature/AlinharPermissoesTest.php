<?php

namespace Tests\Feature;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * AS PERMISSÕES QUE O CÓDIGO PEDE TÊM DE EXISTIR NA BASE.
 *
 * Em produção faltavam 58 (RH inteiro, guias, painéis do hotel, da oficina e do
 * salão): 403 para todos os papéis de todas as empresas. `permissoes:alinhar`
 * cria-as e reparte-as — e a seco não escreve nada.
 */
class AlinharPermissoesTest extends TenantTestCase
{
    public function test_a_seco_nao_escreve_e_a_aplicar_cria_e_reparte_por_equivalencia(): void
    {
        Permission::where('name', 'hotel.dashboard.view')->delete();
        Permission::where('name', 'treasury.transfers.delete')->delete();

        // Um papel feito à mão numa empresa, que nenhum sincronizador conhece.
        $papel = Role::create(['name' => 'Recepção à medida '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $papel->givePermissionTo(Permission::findOrCreate('hotel.dashboard', 'web'));
        $papel->givePermissionTo(Permission::findOrCreate('treasury.transfers.create', 'web'));

        $this->artisan('permissoes:alinhar')->assertSuccessful();

        $this->assertFalse(Permission::where('name', 'hotel.dashboard.view')->exists(), 'a seco não cria');

        $this->artisan('permissoes:alinhar', ['--aplicar' => true])->assertSuccessful();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $papel = $papel->fresh();

        $this->assertTrue($papel->hasPermissionTo('hotel.dashboard.view'), 'quem via o painel antigo vê o novo');
        $this->assertTrue($papel->hasPermissionTo('treasury.transfers.delete'), 'quem cria transferências anula-as');

        // Idempotente: correr outra vez não rebenta nem duplica.
        $this->artisan('permissoes:alinhar', ['--aplicar' => true])->assertSuccessful();
        $this->assertSame(1, Permission::where('name', 'hotel.dashboard.view')->count());
    }
}

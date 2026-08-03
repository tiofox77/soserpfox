<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * Trocar de empresa continua a levar as permissões da empresa certa.
 *
 * O par das correcções de isolamento: não basta negar o que é da outra
 * empresa, tem de continuar a PERMITIR o que é da activa. O recuo do
 * TenantTeamResolver apontava para `users.tenant_id`, e por isso quem tinha a
 * permissão só na empresa para onde trocou levava um 403 indevido.
 */
class TenantSwitchTest extends TenantTestCase
{
    public function test_quem_so_tem_a_permissao_na_empresa_activa_nao_leva_403(): void
    {
        $b = Tenant::create([
            'name' => 'Empresa B', 'slug' => 'b-' . uniqid(),
            'nif' => (string) random_int(600000000, 699999999),
            'email' => 'b' . uniqid() . '@x.ao', 'is_active' => true,
        ]);
        $this->user->tenants()->syncWithoutDetaching([$b->id]);

        // A permissão existe SÓ na empresa B. Na A (users.tenant_id) não há nada.
        setPermissionsTeamId($b->id);
        foreach (['invoicing.stock.view', 'invoicing.stock.edit'] as $nome) {
            \Spatie\Permission\Models\Permission::findOrCreate($nome, 'web');
        }
        $this->user->givePermissionTo(['invoicing.stock.view', 'invoicing.stock.edit']);

        session(['active_tenant_id' => $b->id]);

        // Estado de um pedido novo: nada fixou a equipa, o resolvedor decide.
        setPermissionsTeamId(null);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs(User::findOrFail($this->user->id));

        $this->assertSame($b->id, getPermissionsTeamId(),
            'o recuo tem de apontar para a empresa ACTIVA, não para a de origem');

        $this->assertTrue(
            auth()->user()->can('invoicing.stock.edit'),
            'a permissão existe na empresa activa: não pode dar 403'
        );
    }
}

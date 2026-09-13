<?php

namespace Tests\Feature\Utilizadores;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * NINGUÉM SOBE NA ESCADA PELAS PRÓPRIAS MÃOS.
 *
 * O ecrã novo deu ao Gestor `users.edit` e `users.invite`, e as portas
 * gravavam os papéis que viessem no pedido: um Gestor dava a si próprio o
 * «Super Admin», convidava alguém com ele, e mudava a senha, o PIN e o estado
 * do Administrador (auditoria de 2026-09-13).
 */
class EscadaDosPapeisTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/utilizadores';

    private Role $superAdmin;

    private Role $caixa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        setPermissionsTeamId($this->tenant->id);

        foreach (['users.manage', 'users.view', 'users.edit', 'users.invite', 'invoicing.pos.access', 'invoicing.settings.edit'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        $this->superAdmin = Role::create(['name' => 'Super Admin '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $this->superAdmin->givePermissionTo(['users.manage', 'users.view', 'users.edit', 'users.invite', 'invoicing.pos.access', 'invoicing.settings.edit']);

        $this->caixa = Role::create(['name' => 'Caixa '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $this->caixa->givePermissionTo(['invoicing.pos.access']);

        // Quem edita é um Gestor: vê, edita e convida, mas não gere papéis.
        $this->comPermissoes('users.view', 'users.edit', 'users.invite', 'invoicing.pos.access');

        $this->admin = User::create([
            'name' => 'Administradora', 'email' => 'admin'.uniqid().'@empresa.ao', 'password' => bcrypt('segredo-antigo'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $this->admin->tenants()->syncWithoutDetaching([$this->tenant->id]);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole($this->superAdmin);
    }

    private function corpo(User $u, ?Role $papel, array $extra = []): array
    {
        return array_merge([
            'name' => $u->name,
            'email' => $u->email,
            'is_active' => true,
            'empresas' => [$this->tenant->id],
            'papeis' => $papel ? [$this->tenant->id => $papel->id] : [],
        ], $extra);
    }

    public function test_o_gestor_nao_se_da_a_si_proprio_o_super_admin(): void
    {
        $this->putJson(self::RAIZ.'/'.$this->user->id, $this->corpo($this->user, $this->superAdmin))->assertStatus(422);

        setPermissionsTeamId($this->tenant->id);
        $this->assertFalse($this->user->fresh()->hasRole($this->superAdmin), 'ninguém sobe pelas próprias mãos');
    }

    public function test_o_gestor_nao_convida_com_o_super_admin(): void
    {
        $this->postJson(self::RAIZ.'/convites', [
            'name' => 'Intruso', 'email' => 'intruso@fora.ao', 'role_id' => $this->superAdmin->id,
        ])->assertStatus(422);
    }

    public function test_o_gestor_nao_mexe_na_senha_nem_no_pin_nem_no_estado_do_administrador(): void
    {
        $this->putJson(self::RAIZ.'/'.$this->admin->id, $this->corpo($this->admin, $this->superAdmin, [
            'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ]))->assertForbidden();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('segredo-antigo', $this->admin->fresh()->password));

        $this->postJson(self::RAIZ.'/'.$this->admin->id.'/estado')->assertForbidden();
        $this->assertTrue((bool) $this->admin->fresh()->is_active);

        $this->postJson(self::RAIZ.'/'.$this->admin->id.'/pin', ['pin' => '4827', 'pin_confirmation' => '4827'])->assertForbidden();
    }

    public function test_o_gestor_da_um_papel_que_ele_proprio_cobre(): void
    {
        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega'.uniqid().'@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->putJson(self::RAIZ.'/'.$colega->id, $this->corpo($colega, $this->caixa))->assertOk();

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($colega->fresh()->hasRole($this->caixa));
    }

    public function test_quem_gere_papeis_da_qualquer_um(): void
    {
        $this->comPermissoes('users.manage');

        $this->putJson(self::RAIZ.'/'.$this->user->id, $this->corpo($this->user, $this->superAdmin))->assertOk();

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($this->user->fresh()->hasRole($this->superAdmin));
    }
}

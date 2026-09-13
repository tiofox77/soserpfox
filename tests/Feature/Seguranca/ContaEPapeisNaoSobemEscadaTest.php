<?php

namespace Tests\Feature\Seguranca;

use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * A CONTA É UMA, AS EMPRESAS SÃO VÁRIAS — e ninguém sobe pelas próprias mãos.
 *
 * Auditoria de segurança de 2026-09-13:
 *  - um gestor da empresa A mudava a senha de quem é caixa em A e dono de B;
 *  - quem só gere papéis criava um papel com `users.manage` e dava-o a si próprio;
 *  - uma conta desactivada continuava a entrar, e o token da app nunca caía.
 */
class ContaEPapeisNaoSobemEscadaTest extends TenantTestCase
{
    private function outraEmpresaDe(User $u): Tenant
    {
        $b = Tenant::create(['name' => 'Casa B', 'slug' => 'casa-b-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'b' . uniqid() . '@b.ao', 'is_active' => true]);
        $u->tenants()->syncWithoutDetaching([$b->id => ['is_active' => true]]);

        return $b;
    }

    public function test_o_gestor_de_uma_empresa_nao_muda_a_senha_de_quem_trabalha_noutra(): void
    {
        foreach (['users.view', 'users.edit', 'users.delete'] as $n) {
            Permission::findOrCreate($n, 'web');
        }
        $this->comPermissoes('users.view', 'users.edit', 'users.delete');

        $alvo = User::create(['name' => 'Dona da Casa B', 'email' => 'dona' . uniqid() . '@b.ao', 'password' => bcrypt('senha-dela'), 'tenant_id' => $this->tenant->id, 'is_active' => true]);
        $alvo->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);
        $this->outraEmpresaDe($alvo);

        $this->putJson('/api/v1/invoicing/react/utilizadores/' . $alvo->id, [
            'name' => $alvo->name, 'email' => $alvo->email, 'is_active' => true,
            'password' => 'tomada-123', 'password_confirmation' => 'tomada-123',
            'empresas' => [$this->tenant->id], 'papeis' => [],
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('senha-dela', $alvo->fresh()->password), 'a senha da conta não mudou');

        // Desactivar a conta tirava-a das outras casas: recusa. Eliminar só a tira desta.
        $this->postJson('/api/v1/invoicing/react/utilizadores/' . $alvo->id . '/estado')->assertStatus(422);
        $this->assertTrue((bool) $alvo->fresh()->is_active, 'a conta continua activa nas outras casas');

        $this->deleteJson('/api/v1/invoicing/react/utilizadores/' . $alvo->id)->assertOk();
        $this->assertNull($alvo->fresh()->deleted_at, 'a conta não foi apagada');
        $this->assertSame(1, $alvo->fresh()->tenants()->count(), 'continua na outra empresa');
    }

    public function test_quem_so_gere_papeis_nao_se_da_mais_do_que_tem(): void
    {
        foreach (['users.roles.manage', 'users.manage', 'invoicing.pos.access'] as $n) {
            Permission::findOrCreate($n, 'web');
        }
        $this->comPermissoes('users.roles.manage', 'invoicing.pos.access');

        $gestao = Permission::where('name', 'users.manage')->first();

        $this->postJson('/api/v1/invoicing/react/papeis', [
            'name' => 'Escada ' . uniqid(), 'permissoes' => [$gestao->id],
        ])->assertStatus(422);

        setPermissionsTeamId($this->tenant->id);
        $poderoso = Role::create(['name' => 'Poderoso ' . uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $poderoso->givePermissionTo('users.manage');

        $this->postJson('/api/v1/invoicing/react/papeis/utilizadores/' . $this->user->id, ['papeis' => [$poderoso->id]])
            ->assertStatus(422);

        setPermissionsTeamId($this->tenant->id);
        $this->assertFalse($this->user->fresh()->hasRole($poderoso));
    }

    public function test_conta_desactivada_nao_entra_e_o_token_da_app_cai(): void
    {
        $u = User::create(['name' => 'Saiu', 'email' => 'saiu' . uniqid() . '@empresa.ao', 'password' => bcrypt('senha-antiga'), 'tenant_id' => $this->tenant->id, 'is_active' => true]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        $token = $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'senha-antiga'])->assertOk()->json('token');

        $u->forceFill(['is_active' => false])->save();
        auth()->logout();

        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame(0, ApiToken::where('user_id', $u->id)->count(), 'o token apagou-se');

        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'senha-antiga'])->assertStatus(422);
    }

    public function test_o_login_da_app_trava_a_forca_bruta(): void
    {
        auth()->logout();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'alvo@empresa.ao', 'password' => 'errada' . $i])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'alvo@empresa.ao', 'password' => 'mais-uma'])->assertStatus(429);
    }
}

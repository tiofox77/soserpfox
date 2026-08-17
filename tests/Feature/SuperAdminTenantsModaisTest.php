<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Tenants as EcraTenants;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * Os modais de gestão de uma empresa: utilizadores, papéis e limites.
 *
 * O ecrã tem um campo "Máx. Utilizadores" desde sempre — e nunca foi
 * verificado. E o papel escolhido chegava do browser sem ninguém confirmar que
 * pertencia àquela empresa.
 */
class SuperAdminTenantsModaisTest extends TenantTestCase
{
    private Tenant $empresa;
    private Role $papel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Tenant::create([
            'name'      => 'Empresa Gerida',
            'slug'      => 'gerida-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'g' . uniqid() . '@exemplo.ao',
            'is_active' => true,
            'max_users' => 2,
        ]);

        $this->papel = Role::create([
            'name'       => 'Caixa',
            'guard_name' => 'web',
            'tenant_id'  => $this->empresa->id,
        ]);

        $this->actingAs($this->user);
    }

    /** Junta $quantos utilizadores à empresa, directamente. */
    private function comUtilizadores(int $quantos): array
    {
        $criados = [];

        for ($i = 0; $i < $quantos; $i++) {
            $u = User::create([
                'name'      => "Pessoa {$i}",
                'email'     => "p{$i}." . uniqid() . '@exemplo.ao',
                'password'  => bcrypt('secret'),
                'tenant_id' => $this->empresa->id,
            ]);
            $u->tenants()->syncWithoutDetaching([$this->empresa->id]);
            $criados[] = $u;
        }

        return $criados;
    }

    private function ecra()
    {
        return Livewire::test(EcraTenants::class)
            ->set('managingTenantId', $this->empresa->id);
    }

    public function test_o_limite_de_utilizadores_e_respeitado(): void
    {
        // O campo "Máx. Utilizadores" existia na ficha e NUNCA era verificado:
        // escrevia-se 10 e punham-se cem. O que se verificava era o limite de
        // EMPRESAS do utilizador, que é outra coisa.
        $this->comUtilizadores(2);   // a empresa tem max_users = 2

        $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'A Mais')
            ->set('newUserEmail', 'amais' . uniqid() . '@exemplo.ao')
            ->set('newUserPassword', 'secret123')
            ->set('selectedRoleId', $this->papel->id)
            ->call('addUserToTenant');

        $this->assertSame(
            2,
            $this->empresa->users()->count(),
            'não pode passar do limite da empresa'
        );
    }

    public function test_com_lugar_o_utilizador_e_criado(): void
    {
        $this->comUtilizadores(1);   // ainda há um lugar

        $email = 'novo' . uniqid() . '@exemplo.ao';

        $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'Pessoa Nova')
            ->set('newUserEmail', $email)
            ->set('newUserPassword', 'secret123')
            ->set('selectedRoleId', $this->papel->id)
            ->call('addUserToTenant');

        $this->assertSame(2, $this->empresa->users()->count());
        $this->assertNotNull(User::where('email', $email)->first());
    }

    public function test_um_papel_de_outra_empresa_e_recusado(): void
    {
        // O `$roleId` vem do browser. O menu só mostra papéis desta empresa,
        // mas quem monte o pedido à mão podia indicar o de OUTRA — e o
        // `Role::find()` aceitava-o sem perguntar nada.
        $outraEmpresa = Tenant::create([
            'name'      => 'Outra',
            'slug'      => 'outra-' . uniqid(),
            'nif'       => '5' . random_int(100000000, 999999999),
            'email'     => 'o' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $papelAlheio = Role::create([
            'name'       => 'Gerente da Outra',
            'guard_name' => 'web',
            'tenant_id'  => $outraEmpresa->id,
        ]);

        [$pessoa] = $this->comUtilizadores(1);

        $this->ecra()->call('updateUserRole', $pessoa->id, $papelAlheio->id);

        setPermissionsTeamId($this->empresa->id);

        $this->assertSame(
            0,
            $pessoa->fresh()->roles()->where('roles.id', $papelAlheio->id)->count(),
            'o papel de outra empresa não pode ser atribuído'
        );
    }

    public function test_um_papel_da_propria_empresa_e_atribuido(): void
    {
        [$pessoa] = $this->comUtilizadores(1);

        $this->ecra()->call('updateUserRole', $pessoa->id, $this->papel->id);

        setPermissionsTeamId($this->empresa->id);

        $this->assertSame(
            1,
            $pessoa->fresh()->roles()->where('roles.id', $this->papel->id)->count()
        );
    }

    public function test_criar_utilizador_sem_papel_valido_nao_cria_conta_orfa(): void
    {
        // O papel é validado ANTES de se criar seja o que for: recusar depois
        // deixava uma conta sem permissões e sem empresa.
        $antes = User::count();

        $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'Sem Papel')
            ->set('newUserEmail', 'sp' . uniqid() . '@exemplo.ao')
            ->set('newUserPassword', 'secret123')
            ->set('selectedRoleId', 999999)     // não existe
            ->call('addUserToTenant');

        $this->assertSame($antes, User::count(), 'não pode sobrar uma conta órfã');
    }

    public function test_nao_se_remove_a_ultima_pessoa_da_empresa(): void
    {
        // Não havia guarda nenhuma: dava para tirar o último utilizador e a
        // empresa ficava sem forma de lá entrar.
        [$unica] = $this->comUtilizadores(1);

        $this->ecra()->call('removeUserFromTenant', $unica->id);

        $this->assertSame(
            1,
            $this->empresa->users()->count(),
            'a última pessoa não pode ser removida'
        );
    }

    public function test_com_duas_pessoas_uma_pode_sair(): void
    {
        [$a, $b] = $this->comUtilizadores(2);

        $this->ecra()->call('removeUserFromTenant', $b->id);

        $this->assertSame(1, $this->empresa->users()->count());
        $this->assertNotNull(User::find($b->id), 'sair de uma empresa não apaga a pessoa');
    }

    public function test_a_senha_escrita_nao_fica_no_componente(): void
    {
        // Uma propriedade pública do Livewire viaja para dentro da página. A
        // senha de uma conta nova não tem de lá continuar depois de usada.
        $this->comUtilizadores(0);

        $componente = $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'Pessoa')
            ->set('newUserEmail', 'x' . uniqid() . '@exemplo.ao')
            ->set('newUserPassword', 'SenhaSecreta123')
            ->set('selectedRoleId', $this->papel->id)
            ->call('addUserToTenant');

        $this->assertSame('', $componente->get('newUserPassword'));
        $this->assertStringNotContainsString('SenhaSecreta123', $componente->html());
    }

    public function test_o_plano_manda_quando_e_maior_que_a_ficha(): void
    {
        // Medido em produção: três das seis empresas têm a ficha ABAIXO do que
        // pagam. A Farmácia Neves Bendinha diz 3 na ficha, tem plano Business
        // com 50, e já lá trabalham 5 pessoas.
        //
        // Impor só a ficha bloqueava essas empresas contra um número que não é
        // o que compraram — uma regressão introduzida ao tornar o limite
        // efectivo. O plano é o que o cliente paga.
        $plano = \App\Models\Plan::create([
            'name'      => 'Plano Grande ' . uniqid(),
            'slug'      => 'grande-' . uniqid(),
            'price'     => 1000,
            'max_users' => 50,
            'is_active' => true,
        ]);

        \App\Models\Subscription::create([
            'tenant_id'          => $this->empresa->id,
            'plan_id'            => $plano->id,
            'amount'             => 1000,
            'status'             => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        $this->empresa->update(['max_users' => 2]);   // ficha desactualizada
        $this->comUtilizadores(2);

        $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'Terceira Pessoa')
            ->set('newUserEmail', 'tp' . uniqid() . '@exemplo.ao')
            ->set('newUserPassword', 'secret123')
            ->set('selectedRoleId', $this->papel->id)
            ->call('addUserToTenant');

        $this->assertSame(
            3,
            $this->empresa->fresh()->users()->count(),
            'o limite do plano tem de valer sobre a ficha desactualizada'
        );
    }

    public function test_uma_ficha_acima_do_plano_tambem_vale(): void
    {
        // O contrário: a ficha serve para conceder MAIS do que o plano dá.
        // Nunca menos.
        $plano = \App\Models\Plan::create([
            'name'      => 'Plano Pequeno ' . uniqid(),
            'slug'      => 'pequeno-' . uniqid(),
            'price'     => 100,
            'max_users' => 1,
            'is_active' => true,
        ]);

        \App\Models\Subscription::create([
            'tenant_id'          => $this->empresa->id,
            'plan_id'            => $plano->id,
            'amount'             => 100,
            'status'             => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        $this->empresa->update(['max_users' => 5]);   // concedido à parte
        $this->comUtilizadores(2);

        $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'Mais Uma')
            ->set('newUserEmail', 'mu' . uniqid() . '@exemplo.ao')
            ->set('newUserPassword', 'secret123')
            ->set('selectedRoleId', $this->papel->id)
            ->call('addUserToTenant');

        $this->assertSame(3, $this->empresa->fresh()->users()->count());
    }

    /** Dá à empresa um plano com os limites indicados. */
    private function comPlano(int $utilizadores, int $armazenamento = 1000): \App\Models\Plan
    {
        $plano = \App\Models\Plan::create([
            'name'           => 'Plano ' . uniqid(),
            'slug'           => 'p-' . uniqid(),
            'price'          => 1000,
            'max_users'      => $utilizadores,
            'max_storage_mb' => $armazenamento,
            'is_active'      => true,
        ]);

        \App\Models\Subscription::create([
            'tenant_id'          => $this->empresa->id,
            'plan_id'            => $plano->id,
            'amount'             => 1000,
            'status'             => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        return $plano;
    }

    public function test_gravar_a_ficha_sobe_o_limite_ate_ao_do_plano(): void
    {
        // A ficha mostrava 10 a quem paga por 50, e era o plano que prevalecia
        // na hora de adicionar alguém. Um número que não vale lê-se mal.
        $this->comPlano(50);
        $this->empresa->update(['max_users' => 10]);

        Livewire::test(EcraTenants::class)
            ->call('edit', $this->empresa->id)
            ->set('max_users', 10)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(50, (int) $this->empresa->fresh()->max_users);
    }

    public function test_gravar_a_ficha_nunca_desce_um_limite_concedido(): void
    {
        // A ficha serve para conceder MAIS do que o plano dá. Um limite maior
        // atribuído à parte não pode ser cortado por se gravar a ficha.
        $this->comPlano(5);
        $this->empresa->update(['max_users' => 30]);

        Livewire::test(EcraTenants::class)
            ->call('edit', $this->empresa->id)
            ->set('max_users', 30)
            ->call('save');

        $this->assertSame(30, (int) $this->empresa->fresh()->max_users);
    }

    public function test_o_modelo_diz_qual_e_o_limite_que_vale(): void
    {
        // A regra vive no modelo para todos os ecrãs concordarem — estava só
        // dentro deste componente.
        $this->comPlano(50);
        $this->empresa->update(['max_users' => 10]);

        $empresa = $this->empresa->fresh()->load('activeSubscription.plan');

        $this->assertSame(50, $empresa->limiteDeUtilizadores());
        $this->assertTrue($empresa->fichaAbaixoDoPlano());
    }

    public function test_sem_plano_vale_o_numero_da_ficha(): void
    {
        $this->empresa->update(['max_users' => 7]);

        $this->assertSame(7, $this->empresa->fresh()->limiteDeUtilizadores());
        $this->assertFalse($this->empresa->fresh()->fichaAbaixoDoPlano());
    }

    public function test_uma_empresa_sem_limite_definido_aceita_utilizadores(): void
    {
        // `max_users` a zero ou nulo significa "sem limite", não "nenhum".
        $this->empresa->update(['max_users' => 0]);
        $this->comUtilizadores(3);

        $this->ecra()
            ->set('createNewUser', 1)
            ->set('newUserName', 'Mais Um')
            ->set('newUserEmail', 'mu' . uniqid() . '@exemplo.ao')
            ->set('newUserPassword', 'secret123')
            ->set('selectedRoleId', $this->papel->id)
            ->call('addUserToTenant');

        $this->assertSame(4, $this->empresa->users()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
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

    /**
     * O dono da plataforma — quem abre estes modais.
     *
     * Os ensaios chamavam o componente em Livewire directamente, sem passar pela
     * guarda. A API está atrás do `superadmin`, e é por ela que se passa agora.
     */
    private function dono(): static
    {
        $dono = User::create([
            'name' => 'Dono', 'email' => 'dono_' . uniqid() . '@exemplo.ao', 'password' => bcrypt('x'),
        ]);
        $dono->forceFill(['is_super_admin' => true])->save();

        return $this->actingAs($dono);
    }

    private function juntarNova(string $nome, string $email, string $senha, int $papel)
    {
        return $this->dono()->postJson("/api/v1/plataforma/react/empresas/{$this->empresa->id}/utilizadores", [
            'novo' => true, 'nome' => $nome, 'email' => $email, 'senha' => $senha, 'papel' => $papel,
        ]);
    }

    private function mudarPapel(User $pessoa, int $papel)
    {
        return $this->dono()->putJson("/api/v1/plataforma/react/empresas/{$this->empresa->id}/utilizadores/{$pessoa->id}/papel", ['papel' => $papel]);
    }

    private function retirar(User $pessoa)
    {
        return $this->dono()->deleteJson("/api/v1/plataforma/react/empresas/{$this->empresa->id}/utilizadores/{$pessoa->id}");
    }

    /** Grava a ficha da empresa como o formulário a manda. */
    private function gravarFicha(array $troca)
    {
        $e = $this->empresa->fresh();

        return $this->dono()->putJson("/api/v1/plataforma/react/empresas/{$e->id}", array_merge([
            'name' => $e->name, 'slug' => $e->slug, 'email' => $e->email, 'country' => 'AO',
            'max_users' => (int) $e->max_users, 'max_storage_mb' => max(100, (int) $e->max_storage_mb),
            'is_active' => true,
        ], $troca));
    }

    public function test_o_limite_de_utilizadores_e_respeitado(): void
    {
        // O campo "Máx. Utilizadores" existia na ficha e NUNCA era verificado:
        // escrevia-se 10 e punham-se cem. O que se verificava era o limite de
        // EMPRESAS do utilizador, que é outra coisa.
        $this->comUtilizadores(2);   // a empresa tem max_users = 2

        $this->juntarNova('A Mais', 'amais' . uniqid() . '@exemplo.ao', 'secret123', $this->papel->id);

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

        $this->juntarNova('Pessoa Nova', $email, 'secret123', $this->papel->id);

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

        $this->mudarPapel($pessoa, $papelAlheio->id);

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

        $this->mudarPapel($pessoa, $this->papel->id);

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
        $this->juntarNova('Sem Papel', 'sp' . uniqid() . '@exemplo.ao', 'secret123', 999999)   // não existe
            ->assertStatus(422)
            ->assertJsonValidationErrors('papel');

        // Conta-se pela pessoa e não pelo total: o dono que faz o pedido também
        // é uma conta, criada no próprio ensaio.
        $this->assertSame(0, User::where('name', 'Sem Papel')->count(), 'não pode sobrar uma conta órfã');
    }

    public function test_nao_se_remove_a_ultima_pessoa_da_empresa(): void
    {
        // Não havia guarda nenhuma: dava para tirar o último utilizador e a
        // empresa ficava sem forma de lá entrar.
        [$unica] = $this->comUtilizadores(1);

        $this->retirar($unica);

        $this->assertSame(
            1,
            $this->empresa->users()->count(),
            'a última pessoa não pode ser removida'
        );
    }

    public function test_com_duas_pessoas_uma_pode_sair(): void
    {
        [$a, $b] = $this->comUtilizadores(2);

        $this->retirar($b);

        $this->assertSame(1, $this->empresa->users()->count());
        $this->assertNotNull(User::find($b->id), 'sair de uma empresa não apaga a pessoa');
    }

    public function test_a_senha_escrita_nao_volta_na_resposta(): void
    {
        // A senha de uma conta nova segue por email e não volta ao browser: no
        // Livewire ficava numa propriedade pública, dentro da página.
        $this->comUtilizadores(0);

        $resposta = $this->juntarNova('Pessoa', 'x' . uniqid() . '@exemplo.ao', 'SenhaSecreta123', $this->papel->id);

        $resposta->assertCreated();
        $this->assertStringNotContainsString('SenhaSecreta123', $resposta->getContent());
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

        $this->juntarNova('Terceira Pessoa', 'tp' . uniqid() . '@exemplo.ao', 'secret123', $this->papel->id);

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

        $this->juntarNova('Mais Uma', 'mu' . uniqid() . '@exemplo.ao', 'secret123', $this->papel->id);

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

        $this->gravarFicha(['max_users' => 10])->assertOk();

        $this->assertSame(50, (int) $this->empresa->fresh()->max_users);
    }

    public function test_gravar_a_ficha_nunca_desce_um_limite_concedido(): void
    {
        // A ficha serve para conceder MAIS do que o plano dá. Um limite maior
        // atribuído à parte não pode ser cortado por se gravar a ficha.
        $this->comPlano(5);
        $this->empresa->update(['max_users' => 30]);

        $this->gravarFicha(['max_users' => 30])->assertOk();

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

        $this->juntarNova('Mais Um', 'mu' . uniqid() . '@exemplo.ao', 'secret123', $this->papel->id);

        $this->assertSame(4, $this->empresa->users()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CriarUtilizadoresDaEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name'  => 'Teste Utilizadores ' . uniqid(),
            'email' => uniqid() . '@teste.local',
            'nif'   => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function papel(Tenant $t, string $nome = 'Caixa'): Role
    {
        return Role::create(['name' => $nome, 'guard_name' => 'web', 'tenant_id' => $t->id]);
    }

    public function test_simulacao_nao_cria_ninguem(): void
    {
        $t = $this->empresa();
        $this->papel($t);

        $this->artisan('utilizadores:criar', [
            '--tenant'  => $t->id,
            '--papel'   => 'Caixa',
            '--nomes'   => 'Judite Victoriano',
            '--dominio' => 'kienga.local',
        ])->assertSuccessful();

        $this->assertNull(User::where('email', 'judite.victoriano@kienga.local')->first());
    }

    public function test_cria_com_email_do_nome_e_papel_da_empresa(): void
    {
        $t = $this->empresa();
        $papel = $this->papel($t);

        $this->artisan('utilizadores:criar', [
            '--tenant'  => $t->id,
            '--papel'   => 'Caixa',
            '--nomes'   => 'Judite Victoriano;Rosa Recruta',
            '--dominio' => 'kienga.local',
            '--aplicar' => true,
        ])->assertSuccessful();

        $judite = User::where('email', 'judite.victoriano@kienga.local')->first();

        $this->assertNotNull($judite);
        $this->assertSame('Judite Victoriano', $judite->name);
        $this->assertSame($t->id, (int) $judite->tenant_id);
        $this->assertTrue((bool) $judite->is_active);

        // Ligado à empresa pela tabela de junção, não só pelo tenant_id.
        $this->assertTrue($judite->tenants()->where('tenants.id', $t->id)->exists());

        // E com o papel na equipa certa.
        setPermissionsTeamId($t->id);
        $this->assertTrue($judite->fresh()->hasRole($papel));

        $this->assertNotNull(User::where('email', 'rosa.recruta@kienga.local')->first());
    }

    public function test_cada_um_leva_uma_palavra_passe_diferente(): void
    {
        $t = $this->empresa();
        $this->papel($t);

        $saida = $this->artisan('utilizadores:criar', [
            '--tenant'  => $t->id,
            '--papel'   => 'Caixa',
            '--nomes'   => 'Judite Victoriano;Rosa Recruta',
            '--dominio' => 'kienga.local',
            '--aplicar' => true,
        ]);

        $saida->assertSuccessful()->run();

        $judite = User::where('email', 'judite.victoriano@kienga.local')->first();
        $rosa = User::where('email', 'rosa.recruta@kienga.local')->first();

        // Palavras-passe partilhadas fariam as vendas do POS ficar atribuídas
        // à pessoa errada. Os hashes têm de ser de segredos diferentes.
        $this->assertNotSame($judite->password, $rosa->password);
        $this->assertFalse(Hash::check('', $judite->password));
    }

    public function test_nao_duplica_quem_ja_existe(): void
    {
        $t = $this->empresa();
        $this->papel($t);

        $args = [
            '--tenant'  => $t->id,
            '--papel'   => 'Caixa',
            '--nomes'   => 'Judite Victoriano',
            '--dominio' => 'kienga.local',
            '--aplicar' => true,
        ];

        $this->artisan('utilizadores:criar', $args)->assertSuccessful();
        $this->artisan('utilizadores:criar', $args)->assertSuccessful();

        $this->assertSame(1, User::where('email', 'judite.victoriano@kienga.local')->count());
    }

    public function test_recusa_quando_a_empresa_nao_tem_o_papel(): void
    {
        $t = $this->empresa();

        $this->artisan('utilizadores:criar', [
            '--tenant'  => $t->id,
            '--papel'   => 'PapelQueNaoExiste',
            '--nomes'   => 'Judite Victoriano',
            '--dominio' => 'kienga.local',
            '--aplicar' => true,
        ])->assertFailed();

        $this->assertNull(User::where('email', 'judite.victoriano@kienga.local')->first());
    }

    public function test_nao_usa_o_papel_de_outra_empresa(): void
    {
        $minha = $this->empresa();
        $vizinha = $this->empresa();
        $papelVizinho = $this->papel($vizinha, 'Caixa');

        $this->artisan('utilizadores:criar', [
            '--tenant'  => $minha->id,
            '--papel'   => 'Caixa',
            '--nomes'   => 'Judite Victoriano',
            '--dominio' => 'kienga.local',
            '--aplicar' => true,
        ])->assertFailed();

        $this->assertNull(User::where('email', 'judite.victoriano@kienga.local')->first());
        $this->assertSame($vizinha->id, (int) $papelVizinho->tenant_id);
    }
}

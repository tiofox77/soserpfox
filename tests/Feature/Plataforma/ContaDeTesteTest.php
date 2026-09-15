<?php

namespace Tests\Feature\Plataforma;

use App\Console\Commands\ContaDeTeste;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TenantTestCase;

/**
 * empresas:conta-de-teste — a empresa de experimentar, com quem entra e só os módulos pedidos.
 */
class ContaDeTesteTest extends TenantTestCase
{
    use DatabaseTransactions;

    private function dados(string $email): string
    {
        return base64_encode(json_encode(['empresa' => 'Oficina de Ensaio (Testes)', 'nome' => 'Pessoa de Ensaio', 'email' => $email, 'telefone' => '929 000 000']));
    }

    private function planoComOficina(): Plan
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $plano = Plan::create(['name' => 'Pacote Oficina Ensaio', 'slug' => 'oficina-ensaio-' . uniqid(), 'price_monthly' => 1, 'max_users' => 4, 'max_storage_mb' => 2048, 'is_active' => true]);
        $plano->modules()->sync(Module::whereIn('slug', ['invoicing', 'treasury', 'oficina'])->pluck('id'));

        return $plano;
    }

    public function test_a_seco_nao_cria_nada(): void
    {
        $this->planoComOficina();
        $email = 'ensaio' . uniqid() . '@x.ao';

        $this->artisan('empresas:conta-de-teste', ['--dados' => $this->dados($email)])
            ->expectsOutputToContain('SIMULAÇÃO')
            ->assertExitCode(0);

        $this->assertFalse(User::where('email', $email)->exists());
        $this->assertFalse(Tenant::where('email', $email)->exists());
    }

    public function test_cria_a_empresa_a_conta_e_so_os_modulos_pedidos(): void
    {
        $plano = $this->planoComOficina();
        $email = 'Ensaio' . uniqid() . '@X.ao';

        $codigo = Artisan::call('empresas:conta-de-teste', ['--dados' => $this->dados($email), '--plano' => $plano->slug, '--dias' => 30, '--aplicar' => true]);
        $saida = Artisan::output();
        $this->assertSame(0, $codigo, $saida);

        $user = User::where('email', strtolower($email))->firstOrFail();
        $empresa = Tenant::findOrFail($user->tenant_id);

        preg_match('/senha\s+(\S+)/', $saida, $m);
        $this->assertTrue(Hash::check($m[1] ?? '', $user->password), 'a senha mostrada é a que entra');
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->tenants()->whereKey($empresa->id)->exists());

        setPermissionsTeamId($empresa->id);
        $this->assertTrue($user->fresh()->hasRole('Super Admin'));

        $ligados = $empresa->modules()->wherePivot('is_active', true)->pluck('slug')->sort()->values()->all();
        $this->assertSame(['invoicing', 'oficina', 'treasury'], $ligados);
        $this->assertTrue($empresa->hasModule('oficina'));
        $this->assertFalse($empresa->hasModule('hotel'));

        $sub = $empresa->activeSubscription()->first();
        $this->assertSame('trial', $sub->status);
        $this->assertSame($plano->id, $sub->plan_id);
        $this->assertEquals(0, (float) $sub->amount);
        $this->assertTrue($sub->ends_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_um_email_que_ja_existe_nao_cria_outra_conta(): void
    {
        $this->planoComOficina();

        $this->artisan('empresas:conta-de-teste', ['--dados' => $this->dados(strtoupper($this->user->email)), '--aplicar' => true])
            ->expectsOutputToContain('Já existe uma conta')
            ->assertExitCode(1);
    }

    public function test_a_senha_gerada_passa_a_regra_e_nao_se_repete(): void
    {
        $senhas = array_map(fn () => ContaDeTeste::senha(), range(1, 20));

        foreach ($senhas as $s) {
            $this->assertSame(12, strlen($s));
            $this->assertFalse(Validator::make(['s' => $s], ['s' => [\App\Support\Seguranca\RegraDaSenha::regra()]])->fails(), $s);
        }
        $this->assertCount(20, array_unique($senhas));
    }
}

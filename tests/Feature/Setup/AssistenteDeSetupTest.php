<?php

namespace Tests\Feature\Setup;

use App\Http\Controllers\Setup\AssistenteDeSetupController;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicensePayload;
use App\Services\Licensing\LicenseState;
use App\Services\Setup\CriarPrimeiraEmpresa;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * O ASSISTENTE DE 1.ª UTILIZAÇÃO EM REACT.
 *
 * As rotas só existem na build offline (`licensing.enforce` ligado no arranque),
 * por isso o teste regista-as como o routes/web.php faz. E a base de testes tem
 * empresas: «não há empresa nenhuma» é simulado no serviço, que é quem o decide.
 */
class AssistenteDeSetupTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function () {
            Route::get('/setup', [AssistenteDeSetupController::class, 'index']);
            Route::post('/setup', [AssistenteDeSetupController::class, 'finalizar']);
        });

        $licencas = \Mockery::mock(LicenseManager::class);
        $licencas->shouldReceive('estado')->andReturn(new LicenseState(
            LicenseState::ATIVA, true, 'ok',
            payload: new LicensePayload(['empresa' => 'Padaria do Bairro', 'nif' => '5417000001', 'plano' => 'inexistente']),
        ));
        $this->app->instance(LicenseManager::class, $licencas);

        auth()->logout();
    }

    private function semEmpresas(): void
    {
        $servico = \Mockery::mock(CriarPrimeiraEmpresa::class)->makePartial();
        $servico->shouldReceive('jaHaEmpresa')->andReturn(false);
        $this->app->instance(CriarPrimeiraEmpresa::class, $servico);
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'empresa' => 'Padaria do Bairro',
            'nif' => '5417000001',
            'regime' => Tenant::REGIME_SIMPLIFICADO,
            'telefone' => '923000111',
            'email_empresa' => '',
            'endereco' => 'Rua 1, Luanda',
            'admin_nome' => 'Dona Maria',
            'admin_email' => 'maria'.uniqid().'@padaria.ao',
            'admin_password' => 'SenhaForte9',
            'admin_password_confirmation' => 'SenhaForte9',
        ], $troca);
    }

    public function test_com_empresa_a_pagina_manda_entrar(): void
    {
        $this->get('/setup')->assertRedirect('/login');
    }

    public function test_a_pagina_monta_o_ecra_com_os_dados_da_licenca(): void
    {
        $this->semEmpresas();

        $this->get('/setup')
            ->assertOk()
            ->assertSee('data-ecra="setup/assistente"', false)
            ->assertSee('Padaria do Bairro', false)
            ->assertSee('regime_nao_sujeicao', false);
    }

    public function test_a_licenca_bloqueada_manda_para_a_licenca(): void
    {
        $this->semEmpresas();
        $licencas = \Mockery::mock(LicenseManager::class);
        $licencas->shouldReceive('estado')->andReturn(new LicenseState(LicenseState::BLOQUEADA, false, 'expirada'));
        $this->app->instance(LicenseManager::class, $licencas);

        $this->get('/setup')->assertRedirect('/licenca');
    }

    public function test_os_campos_obrigatorios_e_a_confirmacao_da_senha(): void
    {
        $this->semEmpresas();

        $this->postJson('/setup', $this->dados(['empresa' => '', 'admin_nome' => '', 'regime' => 'inventado', 'admin_password_confirmation' => 'outra']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['empresa', 'admin_nome', 'regime', 'admin_password']);

        $this->postJson('/setup', $this->dados(['admin_email' => $this->user->email]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('admin_email');
    }

    public function test_cria_a_empresa_o_administrador_e_manda_entrar(): void
    {
        $this->semEmpresas();
        $dados = $this->dados();

        $this->postJson('/setup', $dados)->assertOk()->assertJsonPath('ir_para', '/login');

        $admin = User::where('email', $dados['admin_email'])->firstOrFail();
        $empresa = Tenant::findOrFail($admin->tenant_id);

        $this->assertSame('Padaria do Bairro', $empresa->name);
        $this->assertSame(Tenant::REGIME_SIMPLIFICADO, $empresa->regime);
        // Sem email da empresa, fica o do administrador.
        $this->assertSame($dados['admin_email'], $empresa->email);
        $this->assertTrue($admin->tenants()->whereKey($empresa->id)->exists());

        setPermissionsTeamId($empresa->id);
        $this->assertTrue($admin->hasRole('Super Admin'));
    }

    public function test_com_a_empresa_ja_criada_o_segundo_envio_nao_cria_outra(): void
    {
        $antes = Tenant::count();

        $this->postJson('/setup', $this->dados())->assertOk()->assertJsonPath('ir_para', '/login');

        $this->assertSame($antes, Tenant::count());
    }
}

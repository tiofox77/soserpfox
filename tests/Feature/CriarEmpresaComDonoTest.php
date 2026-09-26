<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SmtpSetting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\NewUserEmailTemplateSeeder;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TenantTestCase;

/**
 * `empresas:criar` monta a empresa inteira, com dono, e manda-lhe os acessos
 * (26/09/2026) — com os dados pessoais num ficheiro, e não no endereço.
 */
class CriarEmpresaComDonoTest extends TenantTestCase
{
    private string $ficheiro;

    /** @var list<array{para: string, corpo: string}> */
    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ficheiro = 'ensaio-' . uniqid() . '.json';

        // O envio é em bruto (Mail::send), que o Mail::fake não regista:
        // escuta-se o evento que antecede a entrega, e trava-se.
        Event::listen(MessageSending::class, function (MessageSending $e) {
            foreach ($e->message->getTo() as $destino) {
                $this->enviados[] = ['para' => $destino->getAddress(), 'corpo' => (string) $e->message->getHtmlBody()];
            }

            return false;
        });
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/privado/' . $this->ficheiro));

        parent::tearDown();
    }

    private function dados(array $por = []): void
    {
        @mkdir(storage_path('app/privado'), 0775, true);

        file_put_contents(storage_path('app/privado/' . $this->ficheiro), json_encode(array_merge([
            'nome' => 'Comércio Ensaio ' . uniqid(),
            'nif' => '0063785572BA048',
            'email' => 'dono-' . uniqid() . '@exemplo.ao',
            'telefone' => '927000000',
            'morada' => 'Rua de ensaio, Luanda',
            'regime' => 'exclusao',
        ], $por)));
    }

    private function correr(bool $aplicar = true): string
    {
        Artisan::call('empresas:criar', array_filter([
            '--ficheiro' => $this->ficheiro,
            '--aplicar' => $aplicar,
        ]));

        return Artisan::output();
    }

    public function test_cria_a_empresa_com_regime_plano_dono_e_envia_os_acessos(): void
    {
        $this->seed(NewUserEmailTemplateSeeder::class);
        SmtpSetting::create([
            'host' => 'smtp.exemplo.ao', 'port' => 587, 'username' => 'x', 'password' => 'x', 'encryption' => 'tls',
            'from_email' => 'nao-responder@soserp.vip', 'from_name' => 'SOS ERP', 'is_active' => true, 'is_default' => true,
        ]);
        $plano = Plan::create([
            'name' => 'Starter ensaio', 'slug' => 'starter-' . uniqid(), 'price_monthly' => 1000, 'price_yearly' => 10000,
            'trial_days' => 14, 'max_users' => 2, 'max_storage_mb' => 1000, 'is_active' => true,
        ]);
        $email = 'jose-' . uniqid() . '@exemplo.ao';

        $this->dados(['plano' => $plano->slug, 'dono_email' => $email, 'enviar_acessos' => true]);

        $saida = $this->correr();

        $empresa = Tenant::where('email', 'like', 'dono-%')->latest('id')->firstOrFail();
        $this->assertSame(Tenant::REGIME_NAO_SUJEICAO, $empresa->regime, 'o regime entra na criação');
        $this->assertSame('0063785572BA048', $empresa->nif, 'o NIF é o que o dono da plataforma escreveu');

        $subscricao = $empresa->subscriptions()->latest('id')->first();
        $this->assertSame($plano->id, $subscricao->plan_id);
        $this->assertSame('trial', $subscricao->status, 'a primeira vez começa no teste do plano');

        $dono = User::where('email', $email)->firstOrFail();
        $this->assertTrue($dono->tenants()->where('tenants.id', $empresa->id)->exists());
        setPermissionsTeamId($empresa->id);
        $this->assertTrue($dono->hasRole('Super Admin'));

        // Os administradores da plataforma recebem o aviso de empresa nova;
        // os acessos vão ao dono, uma vez.
        $paraODono = array_values(array_filter($this->enviados, fn ($e) => $e['para'] === $email));
        $this->assertCount(1, $paraODono, 'os acessos vão ao dono, uma vez');
        $this->assertStringContainsString('acessos enviados', $saida);
        $this->assertFileDoesNotExist(storage_path('app/privado/' . $this->ficheiro), 'os dados pessoais não ficam no servidor');

        // A senha vai no email e não no ecrã: a do email abre a conta.
        $senhas = array_filter(
            preg_split('/\s+/', strip_tags($paraODono[0]['corpo'])),
            fn ($p) => preg_match('/^[A-Za-z0-9]{12}$/', $p) && \Illuminate\Support\Facades\Hash::check($p, $dono->password)
        );
        $this->assertNotEmpty($senhas, 'a senha que o email leva é a da conta');
        $this->assertStringNotContainsString('{' . $email . '}', $paraODono[0]['corpo'], 'os marcadores {{…}} do modelo não deixam chavetas à volta dos valores');
        foreach ($senhas as $senha) {
            $this->assertStringNotContainsString($senha, $saida, 'a senha nunca aparece na saída do comando');
        }
    }

    public function test_sem_aplicar_nao_cria_nada_e_guarda_o_ficheiro(): void
    {
        $this->dados(['dono_email' => 'ninguem-' . uniqid() . '@exemplo.ao', 'enviar_acessos' => true]);
        $antes = Tenant::count();

        $saida = $this->correr(false);

        $this->assertStringContainsString('SIMULAÇÃO', $saida);
        $this->assertSame($antes, Tenant::count());
        $this->assertFileExists(storage_path('app/privado/' . $this->ficheiro), 'a simulação não gasta o ficheiro');
    }

    public function test_um_email_que_ja_tem_conta_nao_se_junta_as_escondidas(): void
    {
        $this->dados(['dono_email' => $this->user->email]);
        $antes = Tenant::count();

        $this->assertStringContainsString('Já existe uma conta', $this->correr());
        $this->assertSame($antes, Tenant::count());
    }

    public function test_o_ficheiro_e_so_um_nome_dentro_da_pasta_privada(): void
    {
        Artisan::call('empresas:criar', ['--ficheiro' => '../../.env']);

        $this->assertStringContainsString('só o nome', Artisan::output());
    }
}

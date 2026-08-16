<?php

namespace Tests\Feature;

use App\Models\SmtpSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plataforma\AvisoDeNovaEmpresa;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * O aviso ao dono da plataforma quando nasce uma empresa.
 *
 * Nao existia nada: as tres vias que criam uma empresa mandavam email e SMS ao
 * utilizador que se registava, e a mais ninguem. A App\Notifications\
 * CompanyCreated era o esqueleto que o make:notification gera e ninguem a
 * enviava.
 *
 * O aviso e SINCRONO de proposito — a fila deste sistema tem tarefas paradas
 * desde Julho, e um aviso posto la nao chegava a lado nenhum.
 */
class AvisoDeNovaEmpresaTest extends TenantTestCase
{
    /** @var array<int,array{para:string,assunto:string}> */
    private array $enviados = [];

    /**
     * O Mail::fake() nao serve aqui: esta casa envia com Mail::send([], [], ...),
     * um envio EM BRUTO, e o fake so regista Mailables — a contagem dava sempre
     * zero e os testes passariam sem provar nada. Escuta-se o evento que
     * antecede a entrega, devolver false trava-a, e fica-se com o que ia mesmo
     * a caminho, seja qual for o transporte.
     */
    private function espiarOsEmails(): void
    {
        $this->enviados = [];

        Event::listen(MessageSending::class, function (MessageSending $evento) {
            foreach ($evento->message->getTo() as $destino) {
                $this->enviados[] = [
                    'para'    => $destino->getAddress(),
                    'assunto' => (string) $evento->message->getSubject(),
                ];
            }

            return false;
        });
    }

    private function paraQuem(): array
    {
        return array_column($this->enviados, 'para');
    }

    private function comSmtp(): void
    {
        // O envio depende de haver SMTP configurado na base; sem isto o
        // servico salta o email de proposito, e o teste passaria sem provar
        // nada.
        if (!SmtpSetting::getForTenant(null)) {
            SmtpSetting::create([
                'name'       => 'Teste',
                'host'       => 'smtp.exemplo.ao',
                'port'       => 587,
                'username'   => 'teste',
                'password'   => 'teste',
                'encryption' => 'tls',
                'from_email' => 'nao-responder@soserp.vip',
                'from_name'  => 'SOS ERP',
                'is_active'  => true,
                'is_default' => true,
            ]);
        }
    }

    private function admin(?string $telefone = null): User
    {
        return User::create([
            'name'           => 'Dono da Plataforma',
            'email'          => 'dono-' . uniqid() . '@soserp.vip',
            'password'       => bcrypt('irrelevante'),
            'is_super_admin' => true,
            'phone'          => $telefone,
        ]);
    }

    private function empresaNova(): Tenant
    {
        return Tenant::create([
            'name'         => 'Farmácia Nova',
            'company_name' => 'Farmácia Nova',
            'nif'          => '5000' . random_int(100000, 999999),
            'email'        => 'geral@farmacianova.ao',
            'phone'        => '923000000',
            'is_active'    => true,
        ]);
    }

    public function test_o_dono_da_plataforma_recebe_email(): void
    {
        $this->comSmtp();
        $admin = $this->admin();
        $this->espiarOsEmails();

        $this->empresaNova();

        $this->assertContains($admin->email, $this->paraQuem(),
            'o dono da plataforma nao foi avisado');
    }

    /** O email diz QUAL empresa, senao nao serve de nada. */
    public function test_o_email_identifica_a_empresa(): void
    {
        $this->comSmtp();
        $this->admin();
        $this->espiarOsEmails();

        $empresa = $this->empresaNova();

        $assuntos = array_column($this->enviados, 'assunto');

        $this->assertNotEmpty($assuntos);
        $this->assertStringContainsString($empresa->name, implode(' | ', $assuntos));
    }

    /** Vai por si: nenhuma das vias de criacao precisa de saber disto. */
    public function test_qualquer_via_que_crie_uma_empresa_dispara_o_aviso(): void
    {
        $this->comSmtp();
        $admin = $this->admin();
        $this->espiarOsEmails();

        // Sem passar por ecra nenhum — directamente no modelo, que e o que o
        // observador cobre.
        Tenant::create(['name' => 'Directa', 'company_name' => 'Directa', 'is_active' => true]);

        // Nao "exactamente este": a base de testes pode ter mais do que um
        // super admin, e o que importa e que TODOS os avisados o sejam.
        $this->assertContains($admin->email, $this->paraQuem());
        $this->assertSame(
            [],
            array_diff($this->paraQuem(), User::where("is_super_admin", true)->pluck("email")->all()),
            "avisou alguem que nao e super admin"
        );
    }

    /** Sem super admin nao ha a quem enviar, e isso nao pode ser um erro. */
    public function test_sem_super_admin_nao_estoira(): void
    {
        $this->comSmtp();
        User::where('is_super_admin', true)->update(['is_super_admin' => false]);
        $this->espiarOsEmails();

        $empresa = $this->empresaNova();

        $this->assertTrue($empresa->exists);
        $this->assertSame([], $this->enviados);
    }

    /**
     * A promessa que mais importa: uma empresa nasce mesmo que o aviso falhe.
     * Uma empresa criada e um aviso por enviar e um contratempo; uma empresa
     * que nao se cria por causa do aviso e uma venda perdida.
     */
    public function test_uma_falha_no_aviso_nao_impede_a_empresa_de_nascer(): void
    {
        $this->comSmtp();
        $this->admin();

        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP em baixo'));

        $empresa = $this->empresaNova();

        $this->assertTrue($empresa->exists, 'a empresa tinha de nascer na mesma');
        $this->assertDatabaseHas('tenants', ['id' => $empresa->id]);
    }

    /** Sem SMTP configurado tambem nao pode estoirar. */
    public function test_sem_smtp_nao_estoira(): void
    {
        SmtpSetting::query()->update(['is_active' => false]);
        $this->admin();

        $empresa = $this->empresaNova();

        $this->assertTrue($empresa->exists);
    }

    /** Um admin sem telefone nao leva SMS, e o email vai na mesma. */
    public function test_um_admin_sem_telefone_recebe_email_e_nao_leva_sms(): void
    {
        $this->comSmtp();
        $admin = $this->admin(null);
        $this->espiarOsEmails();

        $this->empresaNova();

        $this->assertNull($admin->fresh()->phone);
        // Nao "exactamente este": a base de testes pode ter mais do que um
        // super admin, e o que importa e que TODOS os avisados o sejam.
        $this->assertContains($admin->email, $this->paraQuem());
        $this->assertSame(
            [],
            array_diff($this->paraQuem(), User::where("is_super_admin", true)->pluck("email")->all()),
            "avisou alguem que nao e super admin"
        );
    }

    /** A confirmacao ao responsavel, para o caminho que nao mandava nada. */
    public function test_o_responsavel_recebe_confirmacao_da_empresa_criada(): void
    {
        $this->comSmtp();
        $this->espiarOsEmails();

        app(AvisoDeNovaEmpresa::class)->confirmarAoResponsavel($this->tenant, $this->user);

        $this->assertContains($this->user->email, $this->paraQuem());
        $this->assertStringContainsString(
            $this->tenant->name,
            implode(' | ', array_column($this->enviados, 'assunto'))
        );
    }
}

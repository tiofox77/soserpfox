<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Reseller;
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
 *
 * E sai NO FIM DO PEDIDO (28/09/2026): so entao a subscricao (o plano) e a
 * ligacao ao revendedor estao gravadas. Nos ensaios que criam a empresa
 * directamente, `$this->app->terminate()` faz de fim do pedido.
 */
class AvisoDeNovaEmpresaTest extends TenantTestCase
{
    /** @var array<int,array{para:string,assunto:string,corpo:string}> */
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
                    'corpo'   => (string) $evento->message->getHtmlBody(),
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

    /** Cria a empresa e termina o pedido — é aí que o aviso sai. */
    private function empresaNova(): Tenant
    {
        $empresa = $this->empresaSemTerminar();
        $this->app->terminate();

        return $empresa;
    }

    private function empresaSemTerminar(): Tenant
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

    private function plano(float $preco = 15000): Plan
    {
        return Plan::create([
            'name' => 'Negócio', 'slug' => 'negocio-' . uniqid(), 'description' => 'Teste',
            'price_monthly' => $preco, 'price_yearly' => $preco * 10, 'trial_days' => 0,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true, 'order' => 9,
        ]);
    }

    private function revendedor(): Reseller
    {
        $r = Reseller::create(['name' => 'João Revende', 'email' => 'rev' . uniqid() . '@exemplo.ao', 'password' => 'Senha-forte-1']);
        $r->forceFill(['status' => 'aprovado', 'code' => Reseller::novoCodigo('João')])->save();

        return $r;
    }

    private function corpoPara(string $email): string
    {
        return implode("\n", array_column(array_filter($this->enviados, fn ($e) => $e['para'] === $email), 'corpo'));
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
        $this->app->terminate();

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

    /**
     * O PLANO E O REVENDEDOR (28/09/2026). No momento em que a empresa nasce
     * nenhum dos dois existe ainda; o aviso espera pelo fim do pedido.
     */
    public function test_o_aviso_espera_pelo_fim_do_pedido_e_leva_o_plano_e_o_revendedor(): void
    {
        $this->comSmtp();
        $admin = $this->admin();
        $this->espiarOsEmails();

        $empresa = $this->empresaSemTerminar();
        $this->assertNotContains($admin->email, $this->paraQuem(), 'ainda sem plano nem revendedor: nao sai ja');

        // O que o registo grava depois da empresa.
        $plano = $this->plano(15000);
        $empresa->subscriptions()->create([
            'plan_id' => $plano->id, 'status' => 'pending', 'amount' => 15000, 'billing_cycle' => 'monthly',
        ]);
        $revendedor = $this->revendedor();
        \App\Services\Revenda\LigacaoAoRevendedor::ligar($empresa, $revendedor, 'link');

        $this->app->terminate();
        $this->app->terminate(); // nos ensaios termina-se mais do que uma vez: um aviso so

        // So os desta empresa: a do setUp do ensaio tambem sai no fim do pedido.
        $desta = array_filter($this->enviados, fn ($e) => $e['para'] === $admin->email && str_contains($e['assunto'], $empresa->name));
        $this->assertCount(1, $desta);
        $corpo = $this->corpoPara($admin->email);
        $this->assertStringContainsString('Negócio — 15.000,00 Kz/mês (a aguardar pagamento)', $corpo);
        $this->assertStringContainsString('João Revende (' . $revendedor->code . ') — link de revendedor', $corpo);
    }

    public function test_sem_revendedor_o_aviso_diz_que_nao_ha(): void
    {
        $this->comSmtp();
        $admin = $this->admin();
        $this->espiarOsEmails();

        $empresa = $this->empresaSemTerminar();
        $empresa->forceFill(['regime' => Tenant::REGIME_SIMPLIFICADO])->save();
        $plano = $this->plano(0);
        $empresa->subscriptions()->create([
            'plan_id' => $plano->id, 'status' => 'trial', 'amount' => 0, 'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(30),
        ]);
        $this->app->terminate();

        $corpo = $this->corpoPara($admin->email);
        $this->assertStringContainsString('Sem revendedor', $corpo);
        $this->assertStringContainsString('gratuito (em teste até ' . now()->addDays(30)->format('d/m/Y') . ')', $corpo);
        // O regime por extenso, e nao a chave interna.
        $this->assertStringContainsString('Regime Simplificado', $corpo);
        $this->assertStringNotContainsString('regime_simplificado', $corpo);
    }

    /** O modelo na base (o que vai em producao) tambem leva as duas linhas. */
    public function test_o_modelo_da_base_leva_o_plano_e_o_revendedor(): void
    {
        $this->seed(\Database\Seeders\AvisoNovaEmpresaTemplateSeeder::class);
        $this->comSmtp();
        $admin = $this->admin();
        $this->espiarOsEmails();

        $empresa = $this->empresaSemTerminar();
        $empresa->subscriptions()->create([
            'plan_id' => $this->plano(9900)->id, 'status' => 'active', 'amount' => 9900, 'billing_cycle' => 'monthly',
        ]);
        $this->app->terminate();

        $corpo = $this->corpoPara($admin->email);
        $this->assertStringContainsString('Negócio — 9.900,00 Kz/mês (activa)', $corpo);
        $this->assertStringContainsString('Sem revendedor', $corpo);
        $this->assertStringNotContainsString('{plano}', $corpo);
    }

    /**
     * A migracao acrescenta as duas linhas ao modelo que ja esta gravado, sem
     * reescrever o resto (pode ter sido mexido no painel de emails).
     */
    public function test_a_migracao_acrescenta_as_linhas_ao_modelo_gravado(): void
    {
        $this->seed(\Database\Seeders\AvisoNovaEmpresaTemplateSeeder::class);
        $modelo = \App\Models\EmailTemplate::where('slug', 'nova-empresa-admin')->first();

        // O modelo como estava em producao: sem as duas linhas, e com uma frase
        // acrescentada no painel que tem de sobreviver.
        $antigo = preg_replace('~\s*<tr><td[^>]*>Plano</td>.*?</tr>\s*<tr><td[^>]*>Revendedor</td>.*?</tr>~s', '', $modelo->body_html);
        $antigo = str_replace('</table>', '</table><p>Frase do painel</p>', $antigo);
        $modelo->update([
            'body_html' => $antigo,
            'body_text' => "Regime: {empresa_regime}\nRegistada: {registada_em}",
            'variables' => ['empresa_nome', 'registada_em'],
        ]);
        $this->assertStringNotContainsString('{plano}', $modelo->fresh()->body_html);

        $migracao = require database_path('migrations/2026_09_28_110000_plano_e_revendedor_no_aviso_de_empresa_nova.php');
        $migracao->up();
        $migracao->up(); // duas vezes nao duplica

        $depois = $modelo->fresh();
        $this->assertSame(1, substr_count($depois->body_html, '{plano}'));
        $this->assertSame(1, substr_count($depois->body_html, '{revendedor}'));
        $this->assertLessThan(strpos($depois->body_html, '>Registada<'), strpos($depois->body_html, '{plano}'));
        $this->assertStringContainsString('Frase do painel', $depois->body_html);
        $this->assertStringContainsString("Plano: {plano}\nRevendedor: {revendedor}\nRegistada:", $depois->body_text);
        $this->assertContains('revendedor', $depois->variables);
    }

    /** O registo a serio, pelo assistente, com o codigo de um revendedor. */
    public function test_o_registo_publico_avisa_com_o_plano_e_o_revendedor(): void
    {
        $this->comSmtp();
        $admin = $this->admin();
        $revendedor = $this->revendedor();
        // Pago, com dias de teste: entra sem comprovativo.
        $plano = $this->plano(24900);
        $plano->forceFill(['trial_days' => 14, 'auto_activate' => true])->save();
        $this->espiarOsEmails();

        auth()->logout();
        $nif = '5' . random_int(100000000, 999999999);
        $resposta = $this->postJson('/register', [
            'passo' => 4, 'name' => 'Ana Silva', 'email' => 'ana' . uniqid() . '@exemplo.ao',
            'password' => 'segredo-forte-123', 'password_confirmation' => 'segredo-forte-123',
            'company_name' => 'Padaria do Revendedor', 'company_nif' => $nif,
            'company_regime' => Tenant::REGIME_SIMPLIFICADO, 'selected_plan_id' => $plano->id,
            'payment_method' => 'transfer',
            'reseller_code' => $revendedor->code, 'aceito_termos' => 1,
        ]);
        $this->assertSame(200, $resposta->status(), json_encode($resposta->json('errors'), JSON_UNESCAPED_UNICODE));

        $empresa = Tenant::where('nif', $nif)->first();
        $this->assertNotNull($empresa, 'o registo tinha de criar a empresa');
        $this->assertSame($revendedor->id, $empresa->reseller_id, 'o revendedor tinha de ficar ligado');

        $corpo = $this->corpoPara($admin->email);
        $this->assertStringContainsString('Negócio — 24.900,00 Kz/mês', $corpo);
        $this->assertStringContainsString('João Revende (' . $revendedor->code . ')', $corpo);
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

    /**
     * O nome da plataforma nao se repete (28/09/2026): o logotipo ja o traz
     * escrito; o layout punha-o outra vez em texto por baixo e o modelo ainda
     * o repetia sob o titulo.
     */
    public function test_o_nome_da_plataforma_nao_se_repete_no_cabecalho(): void
    {
        $this->seed(\Database\Seeders\AvisoNovaEmpresaTemplateSeeder::class);
        $this->comSmtp();
        $admin = $this->admin();
        $this->espiarOsEmails();

        $this->empresaNova();

        $corpo = $this->corpoPara($admin->email);
        $nome = (string) config('app.name', 'SOS ERP');
        $this->assertStringContainsString('<img src=', $corpo, 'o cabecalho leva o logotipo');
        $this->assertStringNotContainsString('class="logo-text"', $corpo);
        $this->assertStringNotContainsString('>' . $nome . '</p>', $corpo);
        $this->assertStringContainsString('Nova empresa registada</h1>', $corpo);
    }

    /** A migracao tira so o paragrafo do nome ao modelo gravado; o resto fica. */
    public function test_a_migracao_tira_o_nome_repetido_do_modelo_gravado(): void
    {
        $this->seed(\Database\Seeders\AvisoNovaEmpresaTemplateSeeder::class);
        $modelo = \App\Models\EmailTemplate::where('slug', 'nova-empresa-admin')->first();

        // O modelo como estava em producao, com uma frase do painel que fica.
        $antigo = str_replace(
            'Nova empresa registada</h1>',
            "Nova empresa registada</h1>\n    <p style=\"margin:4px 0 0;color:#ddd6fe;font-size:13px;\">{app_name}</p>",
            $modelo->body_html
        );
        $antigo = str_replace('</table>', '</table><p>Frase do painel com {app_name}</p>', $antigo);
        $modelo->update(['body_html' => $antigo]);

        $migracao = require database_path('migrations/2026_09_28_130000_nome_repetido_no_aviso_de_empresa_nova.php');
        $migracao->up();
        $migracao->up(); // duas vezes nao estraga

        $depois = $modelo->fresh()->body_html;
        $this->assertStringNotContainsString('>{app_name}</p>', $depois);
        $this->assertStringContainsString('Nova empresa registada</h1>', $depois);
        $this->assertStringContainsString('<p>Frase do painel com {app_name}</p>', $depois);
        $this->assertStringContainsString('{plano}', $depois);
    }
}

<?php

namespace Tests\Feature\Pwa;

use App\Models\ReposicaoDePin;
use App\Models\User;
use App\Support\PinDeTurno;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * Esqueci o PIN, sem rede.
 *
 * O PIN de turno é um bcrypt: não se "acha", só se repõe — e até aqui só com
 * rede. Agora um gestor presente autoriza um PIN novo no aparelho, o bcrypt
 * calcula-se lá, e a reposição chega ao servidor pela fila. O que o servidor
 * garante: que os dois são membros activos, que quem autorizou pode gerir
 * utilizadores AGORA, e que a conta com sessão no aparelho tem o direito de
 * repor PIN de outros (ou é o próprio). E que a fila pode repetir sem que a
 * reposição entre duas vezes.
 */
class ReporPinSemRedeTest extends TenantTestCase
{
    private const ROTA = '/api/v1/invoicing/pin/repor';

    protected function setUp(): void
    {
        parent::setUp();
        // O utilizador da bancada precisa de PIN para aparecer na lista dos
        // funcionários que vai para o aparelho.
        $this->user->definirPinPos('5927');
    }

    /** Um membro activo da empresa, com PIN, gestor ou não. */
    private function funcionario(string $email, string $pin = '7391', bool $gere = false): User
    {
        $u = User::create([
            'name'      => 'Func ' . Str::before($email, '@'),
            'email'     => $email,
            'password'  => bcrypt('segredo-forte'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);
        $u->definirPinPos($pin);

        if ($gere) {
            setPermissionsTeamId($this->tenant->id);
            \Spatie\Permission\Models\Permission::findOrCreate('users.manage', 'web');
            $u->givePermissionTo('users.manage');
            $u->forgetCachedPermissions();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $u;
    }

    /** O verificador tal como o bcryptjs do aparelho o escreve: com `$2a$`. */
    private function verificadorDoAparelho(string $pin): string
    {
        return str_replace('$2y$', '$2a$', password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]));
    }

    private function pedido(User $alvo, User $gestor, string $pinNovo = '8642', array $extra = []): array
    {
        return array_merge([
            'local_uuid'    => (string) Str::uuid(),
            'user_id'       => $alvo->id,
            'authorized_by' => $gestor->id,
            'pin_hash'      => $this->verificadorDoAparelho($pinNovo),
            'reposto_em'    => now()->subMinutes(30)->toIso8601String(),
            'aparelho'      => 'ensaio-1',
        ], $extra);
    }

    // ── O que vai para o aparelho ──────────────────────────────────────

    public function test_o_sync_diz_quem_pode_autorizar_e_as_regras_do_pin(): void
    {
        $this->comPermissoes('users.manage');
        $caixa = $this->funcionario('caixa@empresa.ao');

        $json = $this->actingAs($this->user)->getJson('/api/v1/invoicing/sync')->assertOk()->json();

        $porEmail = collect($json['employees'])->keyBy('email');
        $this->assertTrue($porEmail[$this->user->email]['pode_repor_pin'], 'quem gere utilizadores autoriza');
        $this->assertFalse($porEmail['caixa@empresa.ao']['pode_repor_pin'], 'um caixa não autoriza');

        $this->assertSame(4, $json['pin_regras']['min']);
        $this->assertSame(6, $json['pin_regras']['max']);
        $this->assertContains('1234', $json['pin_regras']['obvios']);
        $this->assertContains('4321', $json['pin_regras']['obvios']);
        $this->assertContains('0000', $json['pin_regras']['obvios']);
        $this->assertNotContains('7391', $json['pin_regras']['obvios']);
    }

    // ── O servidor aceita ──────────────────────────────────────────────

    public function test_o_gestor_com_sessao_repoe_o_pin_do_caixa(): void
    {
        $this->comPermissoes('users.manage');
        $caixa = $this->funcionario('caixa@empresa.ao', '7391');
        $antes = $caixa->pos_pin_hash;

        $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($caixa, $this->user, '8642'))
            ->assertOk()
            ->assertJson(['success' => true, 'estado' => 'aceite']);

        $depois = $caixa->fresh();
        $this->assertNotSame($antes, $depois->pos_pin_hash);
        $this->assertStringStartsWith('$2y$', $depois->pos_pin_hash, 'guarda-se como o Laravel guarda');
        $this->assertTrue(Hash::check('8642', $depois->pos_pin_hash), 'o PIN novo confere no servidor');
        $this->assertFalse(Hash::check('7391', $depois->pos_pin_hash), 'o antigo deixou de valer');

        $registo = ReposicaoDePin::where('user_id', $caixa->id)->first();
        $this->assertSame('aceite', $registo->estado);
        $this->assertSame($this->user->id, $registo->autorizado_por);
        $this->assertSame($this->user->id, $registo->sessao_user_id);
        $this->assertSame('ensaio-1', $registo->aparelho);
        $this->assertNotNull($registo->reposto_no_aparelho_em);
    }

    /** A fila repete: a mesma reposição não entra duas vezes. */
    public function test_a_mesma_reposicao_nao_entra_duas_vezes(): void
    {
        $this->comPermissoes('users.manage');
        $caixa = $this->funcionario('caixa@empresa.ao');
        $pedido = $this->pedido($caixa, $this->user);

        $this->actingAs($this->user)->postJson(self::ROTA, $pedido)->assertOk();
        $hash1 = $caixa->fresh()->pos_pin_hash;

        // Entretanto o caixa mudou de PIN com rede. A repetição da fila não
        // pode voltar a pôr o antigo por cima.
        $caixa->fresh()->definirPinPos('3179');

        $this->actingAs($this->user)->postJson(self::ROTA, $pedido)->assertOk()->assertJson(['estado' => 'aceite']);

        $this->assertSame(1, ReposicaoDePin::where('local_uuid', $pedido['local_uuid'])->count());
        $this->assertTrue(Hash::check('3179', $caixa->fresh()->pos_pin_hash), 'a repetição não reescreveu o PIN');
    }

    /** O próprio, com sessão no aparelho, e um gestor a autorizar: aceita-se. */
    public function test_o_proprio_com_sessao_e_um_gestor_a_autorizar(): void
    {
        $gestor = $this->funcionario('gestor@empresa.ao', '5061', gere: true);

        // $this->user não gere utilizadores, mas é o alvo.
        $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($this->user, $gestor, '8642'))
            ->assertOk();

        $this->assertTrue(Hash::check('8642', $this->user->fresh()->pos_pin_hash));
    }

    // ── O servidor recusa — e diz porquê ───────────────────────────────

    public function test_uma_conta_sem_direito_com_sessao_nao_repoe_o_pin_de_outros(): void
    {
        $gestor = $this->funcionario('gestor@empresa.ao', '5061', gere: true);
        $caixa  = $this->funcionario('caixa@empresa.ao', '7391');
        $antes  = $caixa->pos_pin_hash;

        // $this->user (sem users.manage) tem a sessão; o alvo é outro.
        $r = $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($caixa, $gestor))
            ->assertStatus(422);

        $this->assertStringContainsString('conta com sessão', $r->json('error'));
        $this->assertSame($antes, $caixa->fresh()->pos_pin_hash, 'o PIN ficou como estava');
        $this->assertSame('recusada', ReposicaoDePin::where('user_id', $caixa->id)->value('estado'));
    }

    /** Um gestor demitido tem o verificador no aparelho durante a janela — o servidor sabe melhor. */
    public function test_quem_autorizou_tem_de_poder_gerir_agora(): void
    {
        $this->comPermissoes('users.manage');
        $exGestor = $this->funcionario('ex@empresa.ao', '5061');   // sem users.manage
        $caixa    = $this->funcionario('caixa@empresa.ao');

        $r = $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($caixa, $exGestor))
            ->assertStatus(422);

        $this->assertStringContainsString('não pode gerir utilizadores', $r->json('error'));
    }

    public function test_quem_saiu_da_empresa_nao_recebe_pin(): void
    {
        $this->comPermissoes('users.manage');
        $caixa = $this->funcionario('caixa@empresa.ao');
        $caixa->tenants()->updateExistingPivot($this->tenant->id, ['is_active' => false]);

        $r = $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($caixa, $this->user))
            ->assertStatus(422);

        $this->assertStringContainsString('já não pertence', $r->json('error'));
    }

    public function test_um_verificador_que_nao_e_bcrypt_e_recusado(): void
    {
        $this->comPermissoes('users.manage');
        $caixa = $this->funcionario('caixa@empresa.ao');

        $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($caixa, $this->user, extra: ['pin_hash' => str_repeat('x', 60)]))
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->postJson(self::ROTA, $this->pedido($caixa, $this->user, extra: ['pin_hash' => '8642']))
            ->assertStatus(422);

        $this->assertTrue(Hash::check('7391', $caixa->fresh()->pos_pin_hash));
    }

    public function test_sem_sessao_nao_ha_reposicao(): void
    {
        // A bancada entra autenticada por omissão; aqui quer-se o contrário.
        // Sem o CSRF pelo meio: o que se mede é o `auth`, não o token.
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // O CheckSubscription corre antes do `auth` e responde 419 «Sessão
        // expirada» a quem não tem sessão; o `auth` daria 401. O aparelho lê
        // os dois como sessão morta (fetchJson → handleSessionExpired) — o
        // que importa é que nenhum chega ao controlador.
        $estado = $this->postJson(self::ROTA, [])->getStatusCode();
        $this->assertContains($estado, [401, 419], "sem sessão respondeu $estado");
    }

    // ── A página e o service worker ────────────────────────────────────

    public function test_a_pagina_e_publica_e_instala_o_modo_offline(): void
    {
        $html = $this->get('/invoicing/offline/pin-esquecido')->assertOk()->getContent();

        $this->assertStringContainsString("serviceWorker.register('/sw.js'", $html);
        $this->assertStringContainsString('/js/pwa-invoicing.js?v=', $html, 'o mesmo motor da entrada');
        $this->assertStringContainsString('/js/vendor/bcrypt.min.js', $html, 'o bcrypt corre no aparelho');

        foreach (['cdn.tailwindcss.com', 'unpkg.com', 'cdnjs.cloudflare.com'] as $cdn) {
            $this->assertStringNotContainsString($cdn, $html, "sem rede o $cdn não chega");
        }
    }

    public function test_o_service_worker_guarda_a_pagina_e_nunca_a_serve_no_lugar_de_outra(): void
    {
        $sw = file_get_contents(resource_path('pwa/sw.js'));

        preg_match('/const PRECACHE_PAGINAS = \[(.*?)\];/s', $sw, $pre);
        $this->assertStringContainsString('/invoicing/offline/pin-esquecido', $pre[1] ?? '', 'tem de ficar guardada na instalação');

        preg_match('/const PAGINAS_PUBLICAS = \[(.*?)\];/s', $sw, $pub);
        $this->assertStringContainsString('/invoicing/offline/pin-esquecido', $pub[1] ?? '', 'serve-se só a si própria');

        preg_match('/const FALLBACKS_DE_APLICACAO = \[(.*?)\];/s', $sw, $fal);
        $this->assertStringNotContainsString('pin-esquecido', $fal[1] ?? '', 'nunca no lugar do POS');
    }

    /** A entrada e o ecrã de desbloqueio oferecem o caminho. */
    public function test_a_entrada_e_o_desbloqueio_apontam_para_ca(): void
    {
        $this->get('/invoicing/offline/login')->assertOk()->assertSee('/invoicing/offline/pin-esquecido');

        // O ecrã de desbloqueio vive no layout do PWA: qualquer página dele serve.
        $this->actingAs($this->user)->get('/invoicing/offline')->assertOk()->assertSee('/invoicing/offline/pin-esquecido');
    }

    // ── Uma lista só ───────────────────────────────────────────────────

    public function test_os_pin_obvios_tem_uma_lista_so(): void
    {
        foreach ([
            app_path('Livewire/Users/UserManagement.php'),
            app_path('Livewire/Invoicing/Offline/DefinirPin.php'),
            app_path('Console/Commands/DefinirPinPos.php'),
        ] as $ficheiro) {
            $this->assertStringNotContainsString("['0000', '1111'", file_get_contents($ficheiro),
                basename($ficheiro) . ' voltou a ter a sua própria lista');
        }

        $this->assertTrue(PinDeTurno::ehObvio('1234'));
        $this->assertTrue(PinDeTurno::ehObvio('4321'));
        $this->assertTrue(PinDeTurno::ehObvio('777777'));
        $this->assertTrue(PinDeTurno::ehObvio('98765'));
        $this->assertFalse(PinDeTurno::ehObvio('7391'));
        $this->assertSame('O PIN tem de ter 4 a 6 dígitos.', PinDeTurno::recusa('12'));
        $this->assertSame('Escolha um PIN menos óbvio.', PinDeTurno::recusa('2580'));
        $this->assertNull(PinDeTurno::recusa('7391'));
    }
}

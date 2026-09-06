<?php

namespace Tests\Feature\Clientes;

use App\Livewire\Client\ClientLogin;
use App\Mail\AcessoAoPortalDoCliente;
use App\Models\Client;
use App\Services\Clientes\AcessoAoPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O portal do cliente já existia e o login já funcionava — mas nenhum ecrã
 * escrevia `password` ou `portal_access`, por isso nenhum cliente conseguia
 * entrar. Isto fecha o circuito: a empresa dá o acesso, o cliente recebe os
 * dados por email, e entra.
 *
 * O formulário deixou de ser Livewire: quem liga o acesso é agora a API dos
 * clientes (`portal_access`, `portal_password`, `portal_repor_senha`), e é
 * contra ela que as regras se provam. O serviço `AcessoAoPortal` continua a
 * ser a fonte única — a API só lhe dá a ordem depois de a ficha gravar.
 */
class AcessoAoPortalTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/clients';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // O formulário de clientes é fechado por permissões.
        $this->user->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('invoicing.clients.create', 'web'),
            \Spatie\Permission\Models\Permission::findOrCreate('invoicing.clients.edit', 'web'),
        );
    }

    /** O corpo que a API espera — nomes de campo do controlador. */
    private function corpo(array $por = []): array
    {
        return array_merge([
            'type' => 'pessoa_juridica',
            'name' => 'Cliente do Portal',
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'portal' . uniqid() . '@exemplo.ao',
            'country' => 'AO',
        ], $por);
    }

    private function cliente(array $extra = []): Client
    {
        return Client::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente Teste',
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'cliente' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ], $extra));
    }

    public function test_conceder_gera_senha_liga_o_portal_e_manda_email(): void
    {
        $cliente = $this->cliente();

        $r = app(AcessoAoPortal::class)->conceder($cliente);

        $cliente->refresh();
        $this->assertTrue((bool) $cliente->portal_access);
        $this->assertTrue(Hash::check($r['senha'], $cliente->password));
        $this->assertTrue($r['email_enviado']);

        Mail::assertSent(AcessoAoPortalDoCliente::class,
            fn ($m) => $m->hasTo($cliente->email) && $m->senha === $r['senha']);
    }

    /** A senha nunca fica em claro na base — só o hash. */
    public function test_a_senha_nao_fica_em_claro_na_base(): void
    {
        $cliente = $this->cliente();

        $r = app(AcessoAoPortal::class)->conceder($cliente);

        $guardada = \DB::table('invoicing_clients')->where('id', $cliente->id)->value('password');
        $this->assertNotSame($r['senha'], $guardada);
        $this->assertStringStartsWith('$2y$', $guardada);
    }

    /** Com a senha dada, o cliente entra mesmo no portal. */
    public function test_o_cliente_consegue_entrar_com_a_senha_recebida(): void
    {
        $cliente = $this->cliente();
        $r = app(AcessoAoPortal::class)->conceder($cliente);

        Livewire::test(ClientLogin::class)
            ->set('email', $cliente->email)
            ->set('password', $r['senha'])
            ->call('login')
            ->assertHasNoErrors();

        $this->assertTrue(Auth::guard('client')->check());
        $this->assertSame($cliente->id, Auth::guard('client')->id());
    }

    /** Sem portal ligado não entra, mesmo com a senha certa. */
    public function test_portal_desligado_nao_deixa_entrar(): void
    {
        $cliente = $this->cliente();
        $r = app(AcessoAoPortal::class)->conceder($cliente);
        app(AcessoAoPortal::class)->revogar($cliente);

        Livewire::test(ClientLogin::class)
            ->set('email', $cliente->email)
            ->set('password', $r['senha'])
            ->call('login')
            ->assertHasErrors('email');

        $this->assertFalse(Auth::guard('client')->check());
    }

    /** A empresa pode escolher a senha em vez de a deixar gerar. */
    public function test_empresa_pode_escolher_a_senha(): void
    {
        $cliente = $this->cliente();

        $r = app(AcessoAoPortal::class)->conceder($cliente, 'AMinhaSenha123');

        $this->assertSame('AMinhaSenha123', $r['senha']);
        $this->assertTrue(Hash::check('AMinhaSenha123', $cliente->refresh()->password));
    }

    /** Sem email não há aviso — mas o acesso fica criado na mesma. */
    public function test_cliente_sem_email_fica_com_acesso_e_sem_aviso(): void
    {
        $cliente = $this->cliente(['email' => null]);

        $r = app(AcessoAoPortal::class)->conceder($cliente);

        $this->assertFalse($r['email_enviado']);
        $this->assertTrue((bool) $cliente->refresh()->portal_access);
        $this->assertNotEmpty($r['senha']);
        Mail::assertNothingSent();
    }

    /** Criar o cliente com o acesso ligado faz tudo de uma vez. */
    public function test_formulario_cria_cliente_ja_com_acesso(): void
    {
        $email = 'novo' . uniqid() . '@exemplo.ao';

        $this->postJson(self::RAIZ, $this->corpo(['name' => 'Padaria Central', 'email' => $email, 'portal_access' => true]))
            ->assertCreated()
            ->assertJsonPath('data.portal_access', true);

        $cliente = Client::where('email', $email)->firstOrFail();
        $this->assertTrue((bool) $cliente->portal_access);
        $this->assertNotEmpty($cliente->password);
        Mail::assertSent(AcessoAoPortalDoCliente::class, fn ($m) => $m->hasTo($email));
    }

    /** A empresa pode escolher a senha no próprio formulário. */
    public function test_formulario_aceita_a_senha_escolhida(): void
    {
        $email = 'escolhida' . uniqid() . '@exemplo.ao';

        $this->postJson(self::RAIZ, $this->corpo([
            'email' => $email, 'portal_access' => true, 'portal_password' => 'AMinhaSenha123',
        ]))->assertCreated();

        $cliente = Client::where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('AMinhaSenha123', $cliente->password));
    }

    /** Sem email, ligar o portal é recusado: é por lá que se autentica. */
    public function test_formulario_recusa_portal_sem_email(): void
    {
        $this->postJson(self::RAIZ, $this->corpo(['name' => 'Sem Email Lda', 'email' => '', 'portal_access' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('invoicing_clients', ['name' => 'Sem Email Lda']);
    }

    /**
     * Guardar a ficha de um cliente que já tem acesso NÃO lhe pode trocar a
     * senha por baixo dos pés — só mudar o telefone deixaria o cliente de fora.
     */
    public function test_guardar_a_ficha_nao_troca_a_senha_existente(): void
    {
        $cliente = $this->cliente();
        $r = app(AcessoAoPortal::class)->conceder($cliente);
        $hashAntes = $cliente->refresh()->password;

        $this->putJson(self::RAIZ . '/' . $cliente->id, $this->corpo([
            'name' => $cliente->name, 'nif' => $cliente->nif, 'email' => $cliente->email,
            'phone' => '923000111', 'portal_access' => true,
        ]))->assertOk();

        $this->assertSame($hashAntes, $cliente->refresh()->password);
        $this->assertTrue(Hash::check($r['senha'], $cliente->password));
        $this->assertSame('923000111', $cliente->phone);
    }

    /** Repor a senha a partir da lista invalida a antiga. */
    public function test_repor_senha_invalida_a_antiga(): void
    {
        $cliente = $this->cliente();
        $antiga = app(AcessoAoPortal::class)->conceder($cliente)['senha'];

        $this->putJson(self::RAIZ . '/' . $cliente->id, $this->corpo([
            'name' => $cliente->name, 'nif' => $cliente->nif, 'email' => $cliente->email,
            'portal_repor_senha' => true,
        ]))->assertOk()->assertJsonPath('data.portal_access', true);

        $cliente->refresh();
        $this->assertFalse(Hash::check($antiga, $cliente->password));
        Mail::assertSent(AcessoAoPortalDoCliente::class, fn ($m) => $m->hasTo($cliente->email));
    }

    /** Desligar o acesso fecha a porta sem apagar a ficha. */
    public function test_desligar_o_acesso_fecha_a_porta(): void
    {
        $cliente = $this->cliente();
        app(AcessoAoPortal::class)->conceder($cliente);

        $this->putJson(self::RAIZ . '/' . $cliente->id, $this->corpo([
            'name' => $cliente->name, 'nif' => $cliente->nif, 'email' => $cliente->email,
            'portal_access' => false,
        ]))->assertOk()->assertJsonPath('data.portal_access', false);

        $this->assertFalse((bool) $cliente->refresh()->portal_access);
        $this->assertNotEmpty($cliente->password, 'a senha fica: reabrir não obriga a criar outra');
    }

    /** Um cliente de outra empresa nunca é tocado a partir daqui. */
    public function test_nao_repoe_senha_de_cliente_de_outra_empresa(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = Client::withoutEvents(fn () => Client::create([
            'tenant_id' => $outra->id,
            'name'      => 'Cliente Vizinho',
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'viz' . uniqid() . '@x.ao',
            'password'  => Hash::make('original'),
            'portal_access' => true,
        ]));

        $this->putJson(self::RAIZ . '/' . $alheio->id, $this->corpo([
            'name' => $alheio->name, 'nif' => $alheio->nif, 'email' => $alheio->email,
            'portal_repor_senha' => true,
        ]))->assertNotFound();

        $this->assertTrue(Hash::check('original', $alheio->refresh()->password));
        Mail::assertNothingSent();
    }

    /* ─── O ecrã ──────────────────────────────────────────────────────── */

    /**
     * O QUE O ECRÃ NÃO MOSTRA NÃO EXISTE.
     *
     * A API aceitava `portal_access`, `portal_password` e `portal_repor_senha`
     * desde sempre, mas o formulário em React não tinha nada disto — e uma
     * funcionalidade que nenhum ecrã alcança é uma funcionalidade que ninguém
     * usa: nenhum cliente conseguia receber uma senha.
     */
    public function test_o_formulario_em_react_tem_o_acesso_ao_portal(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/Clientes.tsx'));

        $this->assertStringContainsString('Dar acesso ao portal do cliente', $ecra);
        $this->assertStringContainsString('Repor a senha', $ecra);
        $this->assertStringContainsString('portal_repor_senha', $ecra);
        // E diz que o email é preciso ANTES de o servidor recusar.
        $this->assertStringContainsString('é por lá que o cliente entra no portal', $ecra);
    }

    /**
     * A SENHA EM BRANCO NEM SAI DO ECRÃ.
     *
     * O servidor já se defende (um `portal_password` vazio é tratado como
     * nada), mas quem manda o pedido não pode sequer o incluir: é assim que
     * guardar a ficha nunca pode trocar a senha de quem já entra no portal.
     */
    public function test_o_ecra_nao_manda_senha_que_nao_foi_escrita(): void
    {
        $modulo = file_get_contents(resource_path('js/api/clientes.ts'));

        $this->assertStringContainsString('delete corpo.portal_password', $modulo);
        $this->assertStringContainsString('delete corpo.portal_repor_senha', $modulo);
        $this->assertStringContainsString('paraGravar(dados)', $modulo);
    }
    /**
     * DAR ACESSO SEM ANUNCIAR.
     *
     * Nem todo o acesso se anuncia: prepara-se a conta hoje e entrega-se a
     * senha em mão na visita da semana que vem. O ecrã em Livewire deixava
     * escolher (`portal_avisar`); ao migrar para React a API passou a avisar
     * sempre — e um email de boas-vindas com a senha lá dentro não se
     * desmanda depois de sair.
     *
     * @test
     */
    public function o_ecra_pode_dar_acesso_sem_mandar_email(): void
    {
        $this->comModulo("invoicing");
        $this->comPermissoes("invoicing.clients.create", "invoicing.clients.view");

        Mail::fake();

        $id = $this->postJson("/api/v1/invoicing/react/clients", [
            "name" => "Cliente Calado",
            "type" => "pessoa_juridica",
            "nif" => "5000000700",
            "email" => "calado" . uniqid() . "@exemplo.ao",
            "country" => "AO",
            "portal_access" => true,
            "portal_avisar" => false,
        ])->assertCreated()->json("data.id");

        $cliente = Client::find($id);

        $this->assertTrue((bool) $cliente->portal_access, "o acesso liga-se na mesma");
        $this->assertNotNull($cliente->password);

        Mail::assertNothingSent();
    }

    /** E a omissão continua a ser avisar: quem não manda nada não vê diferença. @test */
    public function por_omissao_o_cliente_continua_a_ser_avisado(): void
    {
        $this->comModulo("invoicing");
        $this->comPermissoes("invoicing.clients.create", "invoicing.clients.view");

        Mail::fake();

        $this->postJson("/api/v1/invoicing/react/clients", [
            "name" => "Cliente Avisado",
            "type" => "pessoa_juridica",
            "nif" => "5000000701",
            "email" => "avisado" . uniqid() . "@exemplo.ao",
            "country" => "AO",
            "portal_access" => true,
        ])->assertCreated();

        Mail::assertSent(AcessoAoPortalDoCliente::class);
    }
}

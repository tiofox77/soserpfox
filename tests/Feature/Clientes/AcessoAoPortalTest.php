<?php

namespace Tests\Feature\Clientes;

use App\Livewire\Client\ClientLogin;
use App\Livewire\Invoicing\Clients;
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
 */
class AcessoAoPortalTest extends TenantTestCase
{
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

        Livewire::test(Clients::class)
            ->call('create')
            ->set('name', 'Padaria Central')
            ->set('nif', (string) random_int(500000000, 599999999))
            ->set('email', $email)
            ->set('portal_access', true)
            ->call('save')
            ->assertHasNoErrors();

        $cliente = Client::where('email', $email)->firstOrFail();
        $this->assertTrue((bool) $cliente->portal_access);
        $this->assertNotEmpty($cliente->password);
        Mail::assertSent(AcessoAoPortalDoCliente::class, fn ($m) => $m->hasTo($email));
    }

    /** Sem email, ligar o portal é recusado: é por lá que se autentica. */
    public function test_formulario_recusa_portal_sem_email(): void
    {
        Livewire::test(Clients::class)
            ->call('create')
            ->set('name', 'Sem Email Lda')
            ->set('nif', (string) random_int(500000000, 599999999))
            ->set('email', '')
            ->set('portal_access', true)
            ->call('save')
            ->assertHasErrors('email');
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

        Livewire::test(Clients::class)
            ->call('edit', $cliente->id)
            ->set('phone', '923000111')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($hashAntes, $cliente->refresh()->password);
        $this->assertTrue(Hash::check($r['senha'], $cliente->password));
    }

    /** Repor a senha a partir da lista invalida a antiga. */
    public function test_repor_senha_invalida_a_antiga(): void
    {
        $cliente = $this->cliente();
        $antiga = app(AcessoAoPortal::class)->conceder($cliente)['senha'];

        Livewire::test(Clients::class)
            ->call('reporSenhaDoPortal', $cliente->id)
            ->assertSet('clienteDaSenha', $cliente->name);

        $cliente->refresh();
        $this->assertFalse(Hash::check($antiga, $cliente->password));
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

        Livewire::test(Clients::class)->call('reporSenhaDoPortal', $alheio->id);

        $this->assertTrue(Hash::check('original', $alheio->refresh()->password));
        Mail::assertNothingSent();
    }
}

<?php

namespace Tests\Feature\Clientes;

use App\Mail\AcessoAoPortalDoCliente;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\Clientes\AcessoAoPortal;
use App\Support\TelefoneDoPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TenantTestCase;

/**
 * ENTRAR NO PORTAL COM O TELEFONE OU UM NOME DE UTILIZADOR (15/09/2026).
 */
class EntradaPorTelefoneEUtilizadorTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/clients';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('portal-cliente:127.0.0.1');

        $this->user->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('invoicing.clients.create', 'web'),
            \Spatie\Permission\Models\Permission::findOrCreate('invoicing.clients.edit', 'web'),
        );
    }

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

    public function test_o_telefone_normaliza_de_qualquer_maneira(): void
    {
        foreach (['923 456 789', '+244 923-456-789', '00244923456789', '(244) 923456789'] as $escrito) {
            $this->assertSame('923456789', TelefoneDoPortal::normalizar($escrito), $escrito);
        }
        $this->assertNull(TelefoneDoPortal::normalizar('1234'));
        $this->assertTrue(TelefoneDoPortal::pareceTelefone('+244 923 456 789'));
        $this->assertFalse(TelefoneDoPortal::pareceTelefone('joao.silva'));
    }

    public function test_entra_com_email_telefone_ou_utilizador_e_a_mesma_senha(): void
    {
        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Joana Lopes', 'nif' => (string) random_int(500000000, 599999999),
            'email' => 'joana' . uniqid() . '@exemplo.ao', 'mobile' => '+244 923 111 222', 'portal_username' => 'Joana.Lopes', 'is_active' => true]);
        $this->assertSame(['923111222', 'joana.lopes'], [$cliente->fresh()->portal_phone, $cliente->fresh()->portal_username]);

        $senha = app(AcessoAoPortal::class)->conceder($cliente, 'Senha-do-portal-9', false)['senha'];
        auth()->logout();

        foreach ([$cliente->email, '923 111 222', '00244923111222', 'JOANA.LOPES'] as $login) {
            $this->postJson('/client/login', ['login' => $login, 'password' => $senha])->assertOk()->assertJsonStructure(['ir_para']);
            $this->assertTrue(Auth::guard('client')->check(), $login);
            Auth::guard('client')->logout();
        }

        $this->postJson('/client/login', ['login' => 'joana.lopes', 'password' => 'errada'])->assertStatus(422)->assertJsonValidationErrors('login');
        // Quem ainda manda `email` continua a entrar.
        $this->postJson('/client/login', ['email' => $cliente->email, 'password' => $senha])->assertOk();
    }

    public function test_sem_email_o_portal_liga_com_utilizador_e_a_senha_volta_a_empresa(): void
    {
        $r = $this->postJson(self::RAIZ, $this->corpo(['email' => '', 'portal_access' => true, 'portal_username' => 'padaria.central']))->assertCreated();

        $this->assertFalse($r->json('portal.email_enviado'));
        $this->assertNotEmpty($r->json('portal.senha'));
        Mail::assertNothingSent();

        auth()->logout();
        $this->postJson('/client/login', ['login' => 'padaria.central', 'password' => $r->json('portal.senha')])->assertOk();
    }

    public function test_o_email_leva_os_tres_dados_e_mudar_o_utilizador_avisa_sem_senha(): void
    {
        $r = $this->postJson(self::RAIZ, $this->corpo(['portal_access' => true, 'mobile' => '923 444 555', 'portal_username' => 'oficina.kz', 'portal_password' => 'Primeira-senha-1']))->assertCreated();
        $this->assertTrue($r->json('portal.email_enviado'));
        $this->assertNull($r->json('portal.senha'));

        Mail::assertSent(AcessoAoPortalDoCliente::class, function (AcessoAoPortalDoCliente $m) {
            $html = $m->render();

            return $m->senha === 'Primeira-senha-1' && str_contains($html, 'oficina.kz') && str_contains($html, '923 444 555') && str_contains($html, 'Primeira-senha-1');
        });

        // Mudar só o nome de utilizador: o cliente recebe os dados novos, sem senha.
        $id = $r->json('data.id');
        $this->putJson(self::RAIZ . "/{$id}", $this->corpo(['nif' => $r->json('data.nif'), 'email' => $r->json('data.email'), 'portal_access' => true, 'mobile' => '923 444 555', 'portal_username' => 'oficina.nova']))
            ->assertOk()->assertJsonPath('portal.email_enviado', true);

        Mail::assertSent(AcessoAoPortalDoCliente::class, fn (AcessoAoPortalDoCliente $m) => $m->senha === null && str_contains($m->render(), 'oficina.nova') && str_contains($m->render(), 'A que já usava'));

        // Gravar sem mudar nada não manda outro email.
        $antes = count(Mail::sent(AcessoAoPortalDoCliente::class));
        $this->putJson(self::RAIZ . "/{$id}", $this->corpo(['nif' => $r->json('data.nif'), 'email' => $r->json('data.email'), 'portal_access' => true, 'mobile' => '923 444 555', 'portal_username' => 'oficina.nova']))->assertOk();
        $this->assertCount($antes, Mail::sent(AcessoAoPortalDoCliente::class));
    }

    public function test_o_nome_de_utilizador_valida_e_e_unico_na_empresa(): void
    {
        $this->postJson(self::RAIZ, $this->corpo(['portal_access' => true, 'portal_username' => '923456789']))->assertStatus(422)->assertJsonValidationErrors('portal_username');
        $this->postJson(self::RAIZ, $this->corpo(['portal_access' => true, 'portal_username' => 'com espaço']))->assertStatus(422)->assertJsonValidationErrors('portal_username');

        $this->postJson(self::RAIZ, $this->corpo(['portal_username' => 'Loja.Um']))->assertCreated();
        $this->postJson(self::RAIZ, $this->corpo(['portal_username' => 'loja.um']))->assertStatus(422)->assertJsonValidationErrors('portal_username');
    }

    public function test_o_mesmo_telefone_em_duas_empresas_escolhe_a_empresa(): void
    {
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $servico = app(AcessoAoPortal::class);

        foreach ([$this->tenant->id, $outra->id] as $t) {
            $c = Client::create(['tenant_id' => $t, 'name' => 'Rui Costa', 'nif' => (string) random_int(500000000, 599999999), 'mobile' => '923777666', 'is_active' => true]);
            $servico->conceder($c, 'Mesma-senha-77', false);
        }

        auth()->logout();
        $r = $this->postJson('/client/login', ['login' => '+244 923 777 666', 'password' => 'Mesma-senha-77'])->assertOk();
        $this->assertCount(2, $r->json('escolher'));
        $this->assertSame('Rui Costa', $r->json('escolher.0.cliente'));
    }
}

<?php

namespace Tests\Feature\Clientes;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TenantTestCase;

/**
 * O PORTAL DO CLIENTE EM REACT — as páginas montam os ecrãs, e a API do portal
 * (`/client/api`) responde só ao cliente, com as contas certas.
 */
class PortalDoClienteTest extends TenantTestCase
{
    private Client $doPortal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doPortal = $this->clienteEmpresa();
        $this->doPortal->forceFill([
            'email' => 'portal'.uniqid().'@exemplo.ao',
            'password' => Hash::make('SenhaDoPortal1'),
            'portal_access' => true,
            'is_active' => true,
        ])->save();
    }

    private function factura(array $troca = [], ?Client $de = null): SalesInvoice
    {
        return SalesInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => ($de ?? $this->doPortal)->id,
            'invoice_number' => 'FT PORTAL/'.random_int(10000, 99999),
            'invoice_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $troca));
    }

    private function comoCliente(): static
    {
        // Pela porta verdadeira, e não por actingAs($c, 'client'): esse muda o guard
        // por omissão do pedido para `client`, o que no browser nunca acontece.
        auth()->logout();
        $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => 'SenhaDoPortal1'])->assertOk();

        return $this;
    }

    public static function paginas(): array
    {
        return [
            'início' => ['/client/dashboard', 'cliente/painel'],
            'extracto' => ['/client/statement', 'cliente/extrato'],
            'eventos' => ['/client/events', 'cliente/eventos'],
            'facturas' => ['/client/invoices', 'cliente/facturas'],
            'proformas' => ['/client/proformas', 'cliente/proformas'],
            'perfil' => ['/client/profile', 'cliente/perfil'],
        ];
    }

    /** @dataProvider paginas */
    public function test_as_paginas_do_portal_montam_o_ecra(string $morada, string $ecra): void
    {
        $this->comoCliente()->get($morada)->assertOk()->assertSee('data-ecra="'.$ecra.'"', false);
    }

    public function test_a_entrada_e_o_esqueci_a_senha_abrem(): void
    {
        auth()->logout();

        $this->get('/client/login')->assertOk()->assertSee('data-ecra="cliente/entrada"', false);
        // Esta ligação apontava para uma vista que nunca existiu — erro 500.
        $this->get('/client/forgot-password')->assertOk()->assertSee('data-ecra="cliente/esqueci-a-senha"', false);
    }

    public function test_sem_sessao_de_cliente_a_api_nao_responde(): void
    {
        auth()->logout();

        $this->assertContains($this->getJson('/client/api/painel')->getStatusCode(), [401, 419]);
    }

    /**
     * O RASCUNHO NÃO APARECE. O portal mostrava a factura em rascunho ao cliente
     * e somava-a ao saldo devedor.
     */
    public function test_o_rascunho_nao_aparece_nem_conta_como_divida(): void
    {
        $this->factura(['status' => 'draft', 'invoice_number' => 'FT RASCUNHO/1', 'total' => 9999]);
        $this->factura(['invoice_number' => 'FT EMITIDA/1', 'total' => 1000]);

        $this->comoCliente();

        $numeros = array_column($this->getJson('/client/api/facturas')->assertOk()->json('facturas'), 'numero');
        $this->assertSame(['FT EMITIDA/1'], $numeros);
        $this->assertSame(1000.0, (float) $this->getJson('/client/api/extrato')->json('numeros.saldo_devedor'));
    }

    /** Uma FR está paga por definição, como na empresa. */
    public function test_uma_fr_conta_como_paga(): void
    {
        $this->factura(['invoice_type' => 'FR', 'status' => 'sent', 'total' => 500, 'paid_amount' => 0]);

        $this->comoCliente();

        $extrato = $this->getJson('/client/api/extrato')->assertOk();
        $this->assertSame(0.0, (float) $extrato->json('numeros.saldo_devedor'));
        $this->assertSame(500.0, (float) $extrato->json('numeros.recebido'));
        $this->assertSame(0.0, (float) $extrato->json('facturas.0.saldo'));
    }

    public function test_uma_factura_vencida_com_saldo_e_atrasada(): void
    {
        $this->factura(['due_date' => now()->subDays(3)->toDateString(), 'total' => 800, 'paid_amount' => 300]);

        $this->comoCliente();

        $r = $this->getJson('/client/api/extrato')->assertOk();
        $this->assertSame(1, $r->json('numeros.atrasadas'));
        $this->assertSame(500.0, (float) $r->json('numeros.valor_atrasado'));
        $this->assertTrue($r->json('facturas.0.atrasada'));
        $this->assertCount(1, $this->getJson('/client/api/facturas?estado=overdue')->json('facturas'));
    }

    public function test_um_cliente_nao_ve_as_facturas_de_outro(): void
    {
        $outro = $this->clienteEmpresa();
        $this->factura(['invoice_number' => 'FT DO OUTRO/1'], $outro);

        $this->comoCliente();

        $this->assertSame([], $this->getJson('/client/api/facturas')->json('facturas'));
    }

    public function test_mudar_a_senha_pede_a_actual(): void
    {
        $this->comoCliente();

        $this->putJson('/client/api/senha', ['current_password' => 'errada', 'new_password' => 'NovaSenha9', 'new_password_confirmation' => 'NovaSenha9'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->putJson('/client/api/senha', ['current_password' => 'SenhaDoPortal1', 'new_password' => 'NovaSenha9', 'new_password_confirmation' => 'NovaSenha9'])
            ->assertOk();

        $this->assertTrue(Hash::check('NovaSenha9', $this->doPortal->fresh()->password));
    }

    public function test_guardar_o_perfil(): void
    {
        $this->comoCliente();

        $this->putJson('/client/api/perfil', ['name' => 'Padaria Nova', 'email' => $this->doPortal->email, 'phone' => '923111222'])->assertOk();

        $this->assertSame('Padaria Nova', $this->doPortal->fresh()->name);
    }

    /** As tentativas de entrada são limitadas: sem isto, adivinhava-se a senha à vontade. */
    public function test_a_entrada_trava_depois_de_cinco_tentativas_erradas(): void
    {
        auth()->logout();
        RateLimiter::clear('portal-cliente:'.strtolower($this->doPortal->email).'|127.0.0.1');

        // A regra das três portas (TravaoDeEntradas): a quinta falha já fecha
        // a porta — responde 429 — durante 10 minutos.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => 'errada'])->assertStatus(422);
        }
        $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => 'errada'])->assertStatus(429);

        // Nem a senha certa entra enquanto dura o travão.
        $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => 'SenhaDoPortal1'])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'Demasiadas tentativas'));
    }
}

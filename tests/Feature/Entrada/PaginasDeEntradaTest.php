<?php

namespace Tests\Feature\Entrada;

use App\Http\Controllers\LicencaController;
use App\Models\Hotel\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicensePayload;
use App\Services\Licensing\LicenseState;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * AS PÁGINAS DE ENTRADA EM REACT — entrar, recuperar a senha, o convite, as
 * portas fechadas, o check-in do hóspede e a licença.
 *
 * Os formulários continuam a ir aos controladores de sempre; o que aqui se
 * prende é que o ecrã recebe o que precisa (erros, o que se escreveu, os
 * recados) — e os defeitos que a migração destapou.
 */
class PaginasDeEntradaTest extends TenantTestCase
{
    private function props(TestResponse $r, string $ecra): array
    {
        $html = $r->assertOk()->getContent();

        $this->assertStringContainsString('data-ecra="'.$ecra.'"', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html, 'a instalação offline não alcança CDNs');
        $this->assertSame(1, preg_match('/data-ecra="'.preg_quote($ecra, '/').'"\s+data-props="([^"]*)"/', $html, $m));

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    public function test_o_login_leva_o_formulario_e_os_caminhos(): void
    {
        auth()->logout();

        $p = $this->props($this->get('/login'), 'entrada/login');

        $this->assertSame(route('login'), $p['acao']);
        $this->assertSame(route('password.request'), $p['recuperar']);
        $this->assertSame(route('register'), $p['registo']);
        $this->assertNotEmpty($p['csrf']);
    }

    public function test_um_login_falhado_volta_com_o_erro_e_o_email_mas_nunca_a_senha(): void
    {
        auth()->logout();

        $this->from('/login')->post('/login', ['email' => 'ninguem@x.com', 'password' => 'errada'])->assertRedirect('/login');

        $p = $this->props($this->get('/login'), 'entrada/login');

        $this->assertNotEmpty($p['erros']['email']);
        $this->assertSame('ninguem@x.com', $p['antigos']['email']);
        $this->assertArrayNotHasKey('password', $p['antigos']);
    }

    /** O login nunca mostrava o `->with('error')` de quem o mandava para lá. */
    public function test_o_recado_de_quem_manda_para_o_login_aparece(): void
    {
        auth()->logout();

        $p = $this->props($this->withSession(['error' => 'A empresa atingiu o limite de utilizadores do seu plano (1).'])->get('/login'), 'entrada/login');

        $this->assertSame([['tipo' => 'erro', 'texto' => 'A empresa atingiu o limite de utilizadores do seu plano (1).']], $p['recados']);
    }

    public function test_as_paginas_da_senha(): void
    {
        auth()->logout();

        $p = $this->props($this->get('/password/reset'), 'entrada/recuperar-senha');
        $this->assertSame(route('password.email'), $p['acao']);

        $p = $this->props($this->get('/password/reset/abc123?email=ana@x.com'), 'entrada/nova-senha');
        $this->assertSame('abc123', $p['token']);
        $this->assertSame('ana@x.com', $p['email']);
        $this->assertSame(route('password.update'), $p['acao']);

        $this->actingAs($this->user);
        $p = $this->props($this->get('/password/confirm'), 'entrada/confirmar-senha');
        $this->assertSame(route('password.confirm'), $p['acao']);
    }

    public function test_a_empresa_desactivada_mostra_o_nome_a_data_e_o_motivo(): void
    {
        auth()->logout();

        $p = $this->props($this->withSession([
            'tenant_deactivated' => true, 'tenant_name' => 'Padaria', 'deactivated_at' => '01/09/2026 10:00', 'deactivation_reason' => 'Falta de pagamento',
        ])->get('/tenant-deactivated'), 'entrada/empresa-desactivada');

        $this->assertSame(['nome' => 'Padaria', 'em' => '01/09/2026 10:00', 'motivo' => 'Falta de pagamento'], $p['empresa']);
        $this->assertNotEmpty($p['contacto']['email']);
    }

    public function test_a_subscricao_expirada_mostra_os_planos_da_montra(): void
    {
        $p = $this->props($this->get('/subscription-expired'), 'entrada/subscricao-expirada');

        $this->assertSame($this->tenant->name, $p['empresa']['nome']);
        $this->assertCount(\App\Models\Plan::publico()->count(), $p['planos']);
        $this->assertStringNotContainsString('923 456 789', json_encode($p), 'o telefone de exemplo saiu');
    }

    private function convite(array $mais = []): UserInvitation
    {
        return UserInvitation::create($mais + [
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->user->id,
            'email' => 'convidado'.uniqid().'@x.com',
            'name' => 'Convidado',
            'role' => 'user',
        ]);
    }

    public function test_o_convite_pendente_leva_o_formulario(): void
    {
        auth()->logout();
        $c = $this->convite();

        $p = $this->props($this->get('/invitation/'.$c->token), 'entrada/convite');

        $this->assertSame('pendente', $p['estado']);
        $this->assertSame(route('invitation.accept.post', $c->token), $p['acao']);
        $this->assertSame($this->tenant->name, $p['convite']['empresa']);
    }

    /** Marcado como expirado na 1.ª visita, voltava a mostrar o formulário na 2.ª. */
    public function test_o_convite_expirado_continua_expirado_na_segunda_visita(): void
    {
        auth()->logout();
        $c = $this->convite();
        $c->forceFill(['expires_at' => now()->subDay()])->save();

        $this->assertSame('expirado', $this->props($this->get('/invitation/'.$c->token), 'entrada/convite')['estado']);
        $this->assertSame('expired', $c->fresh()->status);
        $this->assertSame('expirado', $this->props($this->get('/invitation/'.$c->token), 'entrada/convite')['estado']);
    }

    /** Um convite para «Caixa» apanhava o papel «Caixa» de outra empresa. */
    public function test_o_papel_do_convite_e_o_da_empresa_do_convite(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(), 'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@x.ao', 'is_active' => true,
        ]);
        $alheio = Role::create(['name' => 'Caixa Especial', 'guard_name' => 'web', 'tenant_id' => $outra->id]);

        $this->tenant->update(['max_users' => 0]);
        auth()->logout();

        $c = $this->convite(['role' => 'Caixa Especial']);

        $this->post(route('invitation.accept.post', $c->token), ['password' => 'segredo123', 'password_confirmation' => 'segredo123'])
            ->assertRedirect(route('home'));

        $novo = User::where('email', $c->email)->firstOrFail();
        $ids = \DB::table('model_has_roles')->where('model_id', $novo->id)->pluck('role_id')->all();

        $this->assertNotContains($alheio->id, $ids, 'o convidado levou o papel de outra empresa');
        $this->assertTrue(Role::whereIn('id', $ids)->where('tenant_id', $this->tenant->id)->where('name', 'Caixa Especial')->exists());
    }

    public function test_o_erro_do_check_in_expresso_chega_ao_hospede(): void
    {
        $tipo = \App\Models\Hotel\RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo', 'code' => 'D'.uniqid(),
            'base_price' => 30000, 'capacity' => 2, 'is_active' => true,
        ]);
        $reserva = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'R'.strtoupper(substr(uniqid(), -9)),
            'client_id' => $this->cliente->id, 'room_type_id' => $tipo->id,
            'check_in_date' => now(), 'check_out_date' => now()->addDay(),
            'nights' => 1, 'room_rate' => 30000,
            'status' => 'cancelled', 'payment_status' => 'pending',
        ]);

        auth()->logout();

        // A reserva cancelada não permite check-in: o erro volta à página do hóspede.
        $this->post(route('hotel.express-checkin.confirm', [$reserva->id, $reserva->confirmation_code]))
            ->assertRedirect(route('hotel.express-checkin', [$reserva->id, $reserva->confirmation_code]));
        $this->assertStringContainsString('não permite check-in', (string) session('error'));
        $this->assertSame('cancelled', $reserva->fresh()->status);

        $p = $this->props(
            $this->withSession(['error' => 'Esta reserva não permite check-in.'])->get(route('hotel.express-checkin', [$reserva->id, $reserva->confirmation_code])),
            'entrada/checkin-expresso',
        );

        $this->assertSame([['tipo' => 'erro', 'texto' => 'Esta reserva não permite check-in.']], $p['recados']);
        $this->assertSame(route('hotel.express-checkin.confirm', [$reserva->id, $reserva->confirmation_code]), $p['acao']);
    }

    public function test_a_licenca_em_react_com_os_separadores_e_as_rotas(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/licenca', [LicencaController::class, 'index'])->name('licenca.index');
            Route::post('/licenca', [LicencaController::class, 'guardar'])->name('licenca.guardar');
            Route::post('/licenca/solicitar', [LicencaController::class, 'solicitar'])->name('licenca.solicitar');
            Route::post('/licenca/verificar', [LicencaController::class, 'verificarPedido'])->name('licenca.verificar');
            Route::post('/licenca/sincronizar', [LicencaController::class, 'sincronizar'])->name('licenca.sincronizar');
        });
        app('router')->getRoutes()->refreshNameLookups();

        $licencas = \Mockery::mock(LicenseManager::class);
        $licencas->shouldReceive('estado')->andReturn(new LicenseState(
            LicenseState::ATIVA, true, 'ok', payload: new LicensePayload(['empresa' => 'Padaria do Bairro', 'plano' => 'business', 'modulos' => ['invoicing']]),
        ));
        $this->app->instance(LicenseManager::class, $licencas);
        config(['licensing.checkin_url' => '']);

        $p = $this->props($this->withSession(['aviso' => 'O pedido ainda aguarda aprovação do fornecedor.'])->get('/licenca?ver=1'), 'entrada/licenca');

        $this->assertTrue($p['boa']);
        $this->assertSame('ativa', $p['estado']['tom']);
        $this->assertSame('Padaria do Bairro', $p['estado']['empresa']);
        $this->assertSame('sem_configuracao', $p['ligacao']['estado']);
        $this->assertSame(route('licenca.guardar'), $p['rotas']['guardar']);
        $this->assertSame([['tipo' => 'aviso', 'texto' => 'O pedido ainda aguarda aprovação do fornecedor.']], $p['recados']);
    }
}

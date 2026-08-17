<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Quem entra pelo PWA volta para o PWA.
 *
 * A entrada do PWA abre-se directamente — ninguém foi barrado a caminho de
 * lado nenhum —, por isso não há URL pretendido e o login caía em `/home`.
 * Num telemóvel instalado isso é largar o operador no painel da aplicação
 * web: sair do sítio onde se vende para um sítio onde não se vende.
 */
class LoginDoPwaVoltaAoPwaTest extends TestCase
{
    use DatabaseTransactions;

    private function utilizador(): User
    {
        return User::create([
            'name'      => 'Caixa de Teste',
            'email'     => uniqid() . '@kienga.local',
            'password'  => bcrypt('segredo-de-teste'),
            'is_active' => true,
        ]);
    }

    public function test_entrar_pelo_pwa_leva_ao_pos(): void
    {
        $u = $this->utilizador();

        $this->post('/login', [
            'email'    => $u->email,
            'password' => 'segredo-de-teste',
            'pwa'      => 1,
        ])->assertRedirect(route('invoicing.offline.pos'));

        $this->assertTrue(auth()->check());
    }

    public function test_entrar_pela_web_continua_a_ir_para_onde_ia(): void
    {
        $u = $this->utilizador();

        // Sem o sinal do PWA nada muda: quem entra pela aplicação web segue o
        // caminho de sempre.
        $this->post('/login', [
            'email'    => $u->email,
            'password' => 'segredo-de-teste',
        ])->assertRedirect('/home');
    }

    public function test_o_url_pretendido_continua_a_mandar_na_web(): void
    {
        $u = $this->utilizador();

        session(['url.intended' => url('/invoicing/products')]);

        $this->post('/login', [
            'email'    => $u->email,
            'password' => 'segredo-de-teste',
        ])->assertRedirect(url('/invoicing/products'));
    }

    public function test_o_sinal_do_pwa_ganha_ao_url_pretendido(): void
    {
        $u = $this->utilizador();

        // Alguém abriu a aplicação web, foi barrado, e depois entrou pela
        // entrada do PWA. O que vale é onde ele está agora.
        session(['url.intended' => url('/invoicing/products')]);

        $this->post('/login', [
            'email'    => $u->email,
            'password' => 'segredo-de-teste',
            'pwa'      => 1,
        ])->assertRedirect(route('invoicing.offline.pos'));
    }
}

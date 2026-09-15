<?php

namespace Tests\Feature\Seguranca;

use App\Models\User;
use App\Support\Seguranca\TravaoDeEntradas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TenantTestCase;

/**
 * O CAMPO DE LOGIN: 5 TENTATIVAS E 10 MINUTOS DE BLOQUEIO — e as fugas à volta.
 *
 * Pedido de 2026-09-15. O /login deixava 5 tentativas por MINUTO (7200 senhas
 * por dia a um email), a recuperação de senha dizia que emails têm conta, e o
 * registo aceitava senhas de 6 caracteres e respondia ao browser com o SQL de
 * uma excepção.
 */
class TravaoDeEntradasTest extends TenantTestCase
{
    private User $pessoa;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->logout();

        $this->pessoa = User::create([
            'name' => 'Pessoa do Travão',
            'email' => 'travao' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('Senha-certa-123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->pessoa->tenants()->syncWithoutDetaching([$this->tenant->id]);
    }

    private function tentar(string $senha, ?string $email = null)
    {
        return $this->from('/login')->post('/login', ['email' => $email ?? $this->pessoa->email, 'password' => $senha]);
    }

    public function test_cinco_falhas_bloqueiam_e_nem_a_senha_certa_entra_durante_10_minutos(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->tentar('errada-' . $i)->assertSessionHasErrors('email');
        }

        $r = $this->tentar('Senha-certa-123');
        $r->assertSessionHasErrors('email');
        $this->assertStringContainsString('10 minutos', session('errors')->first('email'));
        $this->assertGuest();

        $this->travel(9)->minutes();
        $this->tentar('Senha-certa-123')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(2)->minutes();
        $this->tentar('Senha-certa-123')->assertRedirect();
        $this->assertAuthenticatedAs($this->pessoa);
    }

    /** O bloqueio conta a partir da QUINTA falha, não do fim da janela da primeira. */
    public function test_o_bloqueio_dura_10_minutos_a_contar_da_quinta_falha(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->tentar('errada');
            $this->travel(2)->minutes();
        }
        $this->tentar('errada'); // 5.ª, aos 8 minutos da primeira

        $this->travel(5)->minutes(); // a janela da primeira já passou; o bloqueio não
        $this->tentar('Senha-certa-123');
        $this->assertGuest();
    }

    public function test_avisa_quando_restam_poucas_tentativas_sem_dizer_se_a_conta_existe(): void
    {
        $this->tentar('errada');
        $this->tentar('errada');
        $this->tentar('errada');
        $comConta = session('errors')->first('email');

        $this->tentar('errada', 'ninguem' . uniqid() . '@exemplo.ao');
        $this->tentar('errada', 'ninguem2' . uniqid() . '@exemplo.ao');
        $semConta = session('errors')->first('email');

        $this->assertStringContainsString('Restam 2 tentativas', $comConta);
        $this->assertStringStartsWith(trans('auth.failed'), $semConta);
    }

    /** A mesma senha em muitas contas: vinte falhas do mesmo IP fecham-lhe a porta. */
    public function test_vinte_falhas_do_mesmo_ip_em_emails_diferentes_bloqueiam_o_ip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->tentar('123456', "alvo{$i}" . uniqid() . '@exemplo.ao');
        }

        $this->tentar('Senha-certa-123');
        $this->assertGuest();
        $this->assertStringContainsString('bloqueada', session('errors')->first('email'));
    }

    public function test_entrar_limpa_as_falhas(): void
    {
        $this->tentar('errada');
        $this->tentar('errada');
        $this->tentar('Senha-certa-123');
        $this->assertAuthenticatedAs($this->pessoa);

        auth()->logout();
        for ($i = 0; $i < 4; $i++) {
            $this->tentar('errada');
        }
        $this->tentar('Senha-certa-123');
        // Depois de entrar, as falhas antigas não contam para o bloqueio.
        $this->assertAuthenticatedAs($this->pessoa);
    }

    public function test_a_api_movel_segue_a_mesma_regra(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $this->pessoa->email, 'password' => 'errada'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/login', ['email' => $this->pessoa->email, 'password' => 'errada'])->assertStatus(429);

        $this->postJson('/api/v1/auth/login', ['email' => $this->pessoa->email, 'password' => 'Senha-certa-123'])
            ->assertStatus(429)->assertJsonStructure(['message', 'bloqueado_segundos']);
    }

    public function test_o_portal_do_cliente_segue_a_mesma_regra(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/client/login', ['email' => 'cliente@exemplo.ao', 'password' => 'errada'])->assertStatus(422);
        }
        $this->postJson('/client/login', ['email' => 'cliente@exemplo.ao', 'password' => 'errada'])->assertStatus(429);
    }

    /** Portas diferentes, contas diferentes: bloquear o portal não fecha o site. */
    public function test_cada_porta_tem_o_seu_travao(): void
    {
        $travao = TravaoDeEntradas::para('portal-cliente');
        for ($i = 0; $i < 5; $i++) {
            $travao->falhou($this->pessoa->email, '127.0.0.1');
        }

        $this->tentar('Senha-certa-123');
        $this->assertAuthenticatedAs($this->pessoa);
    }

    /* ─── Recuperação de senha: nada diz que emails têm conta ─────────── */

    public function test_repor_a_senha_da_a_mesma_resposta_para_email_inexistente_e_link_invalido(): void
    {
        $this->from('/password/reset/x')->post('/password/reset', [
            'token' => 'inventado', 'email' => 'ninguem' . uniqid() . '@exemplo.ao',
            'password' => 'Nova-senha-123', 'password_confirmation' => 'Nova-senha-123',
        ])->assertSessionHasErrors('email');
        $semConta = session('errors')->first('email');

        $this->from('/password/reset/x')->post('/password/reset', [
            'token' => 'inventado', 'email' => $this->pessoa->email,
            'password' => 'Nova-senha-123', 'password_confirmation' => 'Nova-senha-123',
        ])->assertSessionHasErrors('email');
        $comConta = session('errors')->first('email');

        $this->assertSame($semConta, $comConta);
        $this->assertStringContainsString('inválido', $comConta);
    }

    public function test_pedir_o_link_duas_vezes_nao_denuncia_a_conta(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->from('/password/reset')->post('/password/email', ['email' => $this->pessoa->email]);
        $segunda = $this->from('/password/reset')->post('/password/email', ['email' => $this->pessoa->email]);

        $segunda->assertSessionHasNoErrors();
        $segunda->assertSessionHas('status', trans(Password::RESET_LINK_SENT));
    }

    public function test_repor_a_senha_fecha_as_sessoes_e_os_tokens_dos_outros_aparelhos(): void
    {
        DB::table('api_tokens')->insert(['user_id' => $this->pessoa->id, 'name' => 'telemóvel', 'token' => hash('sha256', uniqid()), 'created_at' => now(), 'updated_at' => now()]);
        $token = Password::broker()->createToken($this->pessoa);

        $this->post('/password/reset', [
            'token' => $token, 'email' => $this->pessoa->email,
            'password' => 'Nova-senha-123', 'password_confirmation' => 'Nova-senha-123',
        ])->assertRedirect();

        $this->assertSame(0, DB::table('api_tokens')->where('user_id', $this->pessoa->id)->count());
    }

    /* ─── A regra da senha ───────────────────────────────────────────── */

    public function test_senhas_fracas_sao_recusadas_no_registo(): void
    {
        foreach (['123456', 'abcdefgh', '12345678', 'ab12'] as $fraca) {
            $this->postJson('/register/seguinte', [
                'passo' => 1, 'name' => 'Ana Silva', 'email' => 'ana' . uniqid() . '@exemplo.ao',
                'password' => $fraca, 'password_confirmation' => $fraca,
            ])->assertStatus(422)->assertJsonValidationErrors('password');
        }
    }

    /* ─── O registo contra robôs ─────────────────────────────────────── */

    public function test_a_armadilha_do_registo_recusa_quem_preenche_o_campo_escondido(): void
    {
        $this->postJson('/register/seguinte', [
            'passo' => 1, 'name' => 'Robô Silva', 'email' => 'robo' . uniqid() . '@exemplo.ao',
            'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
            'website' => 'http://spam.example',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_o_proximo_do_registo_tem_limite(): void
    {
        RateLimiter::clear('registo');
        $ultimo = null;
        for ($i = 0; $i < 21; $i++) {
            $ultimo = $this->postJson('/register/seguinte', ['passo' => 1, 'name' => '', 'email' => 'x' . $i . '@exemplo.ao']);
        }

        $ultimo->assertStatus(429);
    }
}

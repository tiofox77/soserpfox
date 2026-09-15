<?php

namespace Tests\Feature\Privacidade;

use App\Models\AgentToken;
use App\Services\Agent\EmissaoDeTokens;
use App\Services\Privacidade\Consentimentos;
use App\Support\Privacidade\Ip;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * PRIVACIDADE: RGPD, LGPD E LEI 22/11 — o que se recolhe e o que a pessoa pode fazer.
 *
 * Pedido de 2026-09-15: localização, morada, IP «e tudo» de cada pessoa. Prova-se
 * que sem consentimento a visita não deixa IP, cidade nem ligação à conta; que
 * com consentimento o IP fica truncado; que a escolha fica provada; e que a
 * pessoa vê, descarrega, muda e pede — com prazo.
 */
class PrivacidadeTest extends TenantTestCase
{
    private function comConsentimento(bool $estatisticas, bool $marketing = false): self
    {
        // withCredentials: sem ele os pedidos JSON do teste não levam cookies.
        return $this->withCredentials()->withUnencryptedCookie(config('privacidade.cookie'), Consentimentos::valorDoCookie($estatisticas, $marketing));
    }

    private function visita(array $extra = []): array
    {
        return array_merge([
            'visitor_id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'type' => 'pageview',
            'url' => 'https://soserp.vip/precos?utm_source=face&email=ana@exemplo.ao',
            'path' => '/precos',
            'referrer' => 'https://www.google.com/search?q=erp+angola',
        ], $extra);
    }

    public function test_o_ip_guardado_e_o_da_rede(): void
    {
        $this->assertSame('102.140.12.0', Ip::anonimizar('102.140.12.87'));
        $this->assertSame('2001:db8:85a3::', Ip::anonimizar('2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
        $this->assertNull(Ip::anonimizar('nao-e-ip'));
        $this->assertNull(Ip::anonimizar(null));
    }

    /** Sem o «sim» às estatísticas: sem IP, sem browser, sem conta, sem o que vem depois do «?». */
    public function test_sem_consentimento_a_visita_e_anonima(): void
    {
        $dados = $this->visita();

        $this->withServerVariables(['REMOTE_ADDR' => '102.140.12.87'])
            ->postJson('/api/analytics/track', $dados)->assertOk();

        $e = DB::table('analytics_events')->where('visitor_id', $dados['visitor_id'])->first();
        $this->assertNotNull($e);
        $this->assertSame(1, (int) $e->anonimo);
        $this->assertNull($e->ip);
        $this->assertNull($e->user_agent);
        $this->assertNull($e->user_id, 'uma visita anónima não se liga à conta de quem tem sessão');
        $this->assertSame('https://soserp.vip/precos', $e->url);
        $this->assertSame('https://www.google.com/', $e->referrer);
    }

    /** O browser a dizer que consentiu não chega: o cookie no servidor também tem de o dizer. */
    public function test_o_browser_sozinho_nao_desliga_o_modo_anonimo(): void
    {
        $dados = $this->visita(['anonimo' => false]);

        $this->postJson('/api/analytics/track', $dados)->assertOk();

        $this->assertSame(1, (int) DB::table('analytics_events')->where('visitor_id', $dados['visitor_id'])->value('anonimo'));
    }

    public function test_com_consentimento_o_ip_fica_truncado(): void
    {
        $dados = $this->visita(['anonimo' => false]);

        $this->comConsentimento(true)
            ->withServerVariables(['REMOTE_ADDR' => '102.140.12.87'])
            ->postJson('/api/analytics/track', $dados)->assertOk();

        $e = DB::table('analytics_events')->where('visitor_id', $dados['visitor_id'])->first();
        $this->assertSame(0, (int) $e->anonimo);
        $this->assertSame('102.140.12.0', $e->ip);
    }

    /** Um consentimento dado a outra versão das regras não vale para esta. */
    public function test_um_cookie_de_outra_versao_nao_conta(): void
    {
        $dados = $this->visita();

        $this->withCredentials()->withUnencryptedCookie(config('privacidade.cookie'), 'v2020-01-01.e1.m1')
            ->postJson('/api/analytics/track', $dados)->assertOk();

        $this->assertSame(1, (int) DB::table('analytics_events')->where('visitor_id', $dados['visitor_id'])->value('anonimo'));
    }

    public function test_a_escolha_do_aviso_fica_provada_e_devolve_o_cookie(): void
    {
        $visitante = (string) Str::uuid();

        $r = $this->postJson('/api/analytics/consentimento', ['estatisticas' => true, 'marketing' => false, 'visitor_id' => $visitante])
            ->assertOk();

        $r->assertCookie(config('privacidade.cookie'), Consentimentos::valorDoCookie(true, false), false);
        $linhas = DB::table('consentimentos')->where('visitor_id', $visitante)->pluck('aceite', 'tipo');
        $this->assertSame(1, (int) $linhas['estatisticas']);
        $this->assertSame(0, (int) $linhas['marketing']);
        $this->assertSame(Consentimentos::versao(), DB::table('consentimentos')->where('visitor_id', $visitante)->value('versao'));
    }

    /* ─── Minha conta → Privacidade ──────────────────────────────────── */

    public function test_o_separador_mostra_o_inventario_e_os_dados_da_pessoa(): void
    {
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $this->user->id, 'ip_address' => '41.63.1.10', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120', 'payload' => '', 'last_activity' => time()]);

        $r = $this->getJson('/api/v1/invoicing/react/conta/privacidade')->assertOk();

        $r->assertJsonStructure(['inventario' => [['titulo', 'dados', 'finalidade', 'base_legal', 'retencao', 'destinatarios']], 'direitos', 'dados' => ['perfil', 'empresas', 'sessoes', 'entradas', 'consentimentos', 'pedidos'], 'tipos_de_pedido']);
        $sessao = collect($r->json('dados.sessoes'))->firstWhere('ip', '41.63.1.10');
        $this->assertNotNull($sessao);
        $this->assertSame('Chrome em Windows', $sessao['aparelho']);
    }

    public function test_a_exportacao_descarrega_tudo_sem_a_senha(): void
    {
        $r = $this->get('/api/v1/invoicing/react/conta/privacidade/exportar')->assertOk();

        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $corpo = $r->getContent();
        $this->assertStringContainsString($this->user->email, $corpo);
        $this->assertStringNotContainsString((string) $this->user->password, $corpo);
        $this->assertStringNotContainsString('pos_pin_hash', $corpo);
        $json = json_decode($corpo, true);
        $this->assertArrayHasKey('entradas_e_saidas', $json);
        $this->assertArrayHasKey('inventario', $json);
    }

    public function test_mudar_os_consentimentos_na_conta(): void
    {
        $this->putJson('/api/v1/invoicing/react/conta/privacidade/consentimentos', ['estatisticas' => false, 'marketing' => true])
            ->assertOk()
            ->assertCookie(config('privacidade.cookie'), Consentimentos::valorDoCookie(false, true), false)
            ->assertJsonPath('consentimentos.marketing.aceite', true)
            ->assertJsonPath('consentimentos.estatisticas.aceite', false);
    }

    public function test_terminar_as_outras_sessoes_deixa_a_actual(): void
    {
        foreach (['41.63.1.10', '41.63.1.11'] as $ip) {
            DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $this->user->id, 'ip_address' => $ip, 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        $this->postJson('/api/v1/invoicing/react/conta/privacidade/sessoes/terminar', ['incluir_aplicacao' => false])
            ->assertOk()->assertJsonPath('sessoes', 2);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->whereIn('ip_address', ['41.63.1.10', '41.63.1.11'])->count());
    }

    public function test_um_pedido_fica_com_prazo_e_chega_a_plataforma(): void
    {
        $this->postJson('/api/v1/invoicing/react/conta/privacidade/pedidos', ['tipo' => 'rectificacao', 'mensagem' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('mensagem');

        $r = $this->postJson('/api/v1/invoicing/react/conta/privacidade/pedidos', ['tipo' => 'apagamento', 'mensagem' => 'Quero apagar a conta.'])
            ->assertCreated();

        $pedido = DB::table('pedidos_de_privacidade')->find($r->json('id'));
        $this->assertSame('recebido', $pedido->estado);
        $this->assertSame(now()->addDays(30)->toDateString(), substr($pedido->prazo_em, 0, 10));
        $this->assertTrue(DB::table('contact_messages')->where('email', $this->user->email)->where('message', 'like', '%#' . $pedido->id . '%')->exists());
    }

    /* ─── O registo guarda a prova ───────────────────────────────────── */

    public function test_o_registo_grava_a_aceitacao_dos_termos_e_da_politica(): void
    {
        auth()->logout();
        $plano = \App\Models\Plan::create([
            'name' => 'Amigo', 'slug' => 'amigo-priv-' . uniqid(), 'description' => 'Grátis',
            'price_monthly' => 0, 'price_yearly' => 0, 'trial_days' => 30,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true, 'auto_activate' => true, 'order' => 1,
        ]);
        $email = 'priv' . uniqid() . '@exemplo.ao';

        $this->postJson('/register', [
            'passo' => 4, 'name' => 'Ana Silva', 'email' => $email,
            'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
            'company_name' => 'Padaria Privada', 'company_nif' => (string) random_int(500000000, 599999999),
            'company_regime' => \App\Models\Tenant::REGIME_SIMPLIFICADO, 'selected_plan_id' => $plano->id,
            'payment_method' => 'transfer', 'aceito_termos' => 1,
        ])->assertOk();

        $user = \App\Models\User::where('email', $email)->firstOrFail();
        $actuais = Consentimentos::actuais($user);
        $this->assertTrue($actuais['termos']['aceite']);
        $this->assertTrue($actuais['privacidade']['aceite']);
        $this->assertSame('registo', $actuais['termos']['origem']);
    }

    /* ─── Páginas e retenção ─────────────────────────────────────────── */

    public function test_as_politicas_dizem_o_que_se_recolhe(): void
    {
        $this->get('/privacidade')->assertOk()->assertSee('RGPD')->assertSee('LGPD')->assertSee('IP')->assertSee('Minha conta');
        $this->get('/cookies')->assertOk()->assertSee('sos_vid')->assertSee('data-abrir-consentimento', false);
    }

    public function test_as_paginas_publicas_trazem_o_aviso_e_os_terceiros_esperam(): void
    {
        $this->get('/register')->assertOk()->assertSee('sos-consentimento-config', false)->assertDontSee('<noscript><img height="1"', false);
    }

    public function test_a_retencao_simula_por_omissao_e_so_apaga_com_aplicar(): void
    {
        $antigo = (string) Str::uuid();
        DB::table('analytics_events')->insert([
            'visitor_id' => $antigo, 'session_id' => (string) Str::uuid(), 'type' => 'pageview', 'path' => '/',
            'ip' => '102.140.12.87', 'created_at' => now()->subMonths(14),
        ]);

        Artisan::call('privacidade:reter');
        $this->assertTrue(DB::table('analytics_events')->where('visitor_id', $antigo)->exists(), 'a simulação não pode apagar nada');

        Artisan::call('privacidade:reter', ['--aplicar' => true]);
        $this->assertFalse(DB::table('analytics_events')->where('visitor_id', $antigo)->exists());
    }

    public function test_o_agente_ve_os_pedidos_de_privacidade(): void
    {
        config(['agent.token.exigir_ips' => false]);
        $r = app(EmissaoDeTokens::class)->emitir('openclaw-priv', $this->user, ['support:read'], [], 30);
        DB::table('pedidos_de_privacidade')->insert([
            'user_id' => $this->user->id, 'email' => $this->user->email, 'tipo' => 'acesso', 'estado' => 'recebido',
            'prazo_em' => now()->subDay(), 'created_at' => now()->subDays(31), 'updated_at' => now(),
        ]);

        $this->getJson('/api/agent/v1/support/privacidade', ['Authorization' => 'Bearer ' . $r['em_claro']])
            ->assertOk()->assertJsonPath('resumo.atrasados', fn ($n) => $n >= 1);
    }
}

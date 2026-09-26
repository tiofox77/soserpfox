<?php

namespace Tests\Feature\Campanha;

use App\Models\AnalyticsEvent;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Campanha\ResultadosDaCampanha;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A DEDUPLICAÇÃO E A RECONCILIAÇÃO (18/09/2026).
 *
 * O CompleteRegistration é UM por empresa (event_id determinístico) e só depois
 * de a inscrição estar gravada — recarregar não gera nova conversão. E o
 * relatório separa eventos, inscrições, empresas e primeira utilização, com os
 * testes de fora.
 */
class ConversoesDaCampanhaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function planoGratuito(): Plan
    {
        return Plan::create([
            'name' => 'Amigo', 'slug' => 'amigo-camp', 'description' => 'x', 'price_monthly' => 0,
            'price_yearly' => 0, 'trial_days' => 180, 'max_users' => 3, 'max_companies' => 1,
            'is_active' => true, 'is_public' => true, 'auto_activate' => true, 'order' => 1,
        ]);
    }

    public function test_o_completeregistration_e_um_por_empresa_e_nao_refoga_ao_recarregar(): void
    {
        $plano = $this->planoGratuito();
        $email = 'ana' . uniqid() . '@cliente.ao';

        $this->postJson('/register', [
            'passo' => 4, 'name' => 'Ana Silva', 'email' => $email,
            'password' => 'segredo-forte-123', 'password_confirmation' => 'segredo-forte-123',
            'company_name' => 'Padaria Nova', 'company_nif' => (string) random_int(500000000, 599999999),
            'company_regime' => Tenant::REGIME_SIMPLIFICADO, 'selected_plan_id' => $plano->id,
            'payment_method' => 'transfer', 'aceito_termos' => 1,
        ])->assertOk();

        $tenant = Tenant::where('name', 'Padaria Nova')->latest('id')->firstOrFail();

        // O event_id é determinístico (uma empresa = uma conversão), e fica à
        // espera na sessão até uma página com o pixel o usar.
        $this->assertSame('registration-' . $tenant->id, session('meta_registration_completed.event_id'));

        \App\Models\SystemSetting::set('facebook_pixel_id', '1234567890');
        $comMarketing = \App\Services\Privacidade\Consentimentos::valorDoCookie(false, true);

        // Sem consentimento de marketing vai no script à espera dele, e não se gasta.
        $semConsentimento = $this->get('/register')->assertOk();
        $this->assertStringContainsString('registration-' . $tenant->id, $semConsentimento->getContent());
        $this->assertNotNull(session('meta_registration_completed'), 'sem consentimento não se gasta');

        // Com consentimento sai UMA vez: recarregar já não repete.
        $primeira = $this->withUnencryptedCookie('sos_consentimento', $comMarketing)->get('/register')->assertOk();
        $this->assertStringContainsString("'CompleteRegistration'", $primeira->getContent());
        $this->assertNull(session('meta_registration_completed'));

        $recarregada = $this->withUnencryptedCookie('sos_consentimento', $comMarketing)->get('/register')->assertOk();
        $this->assertStringNotContainsString("'CompleteRegistration'", $recarregada->getContent(), 'recarregar não conta outra inscrição');

        // E o servidor gravou a conversão UMA vez, com o event_id do pixel.
        $conversoes = AnalyticsEvent::where('event_name', 'complete_registration')->get()
            ->filter(fn ($e) => (int) ($e->meta['tenant_id'] ?? 0) === $tenant->id);
        $this->assertCount(1, $conversoes);
        $this->assertSame('registration-' . $tenant->id, $conversoes->first()->meta['event_id']);
    }

    public function test_a_inscricao_pendente_leva_o_evento_ate_a_pagina_da_subscricao(): void
    {
        $plano = $this->planoGratuito();
        $user = User::create(['name' => 'X', 'email' => 'x' . uniqid() . '@cliente.ao', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Models\SystemSetting::set('facebook_pixel_id', '1234567890');

        $resposta = $this->actingAs($user)
            ->withSession(['meta_registration_completed' => ['event_id' => 'registration-999', 'plan' => $plano->slug, 'status' => 'pending', 'em' => now()->timestamp]])
            ->withUnencryptedCookie('sos_consentimento', \App\Services\Privacidade\Consentimentos::valorDoCookie(false, true))
            ->get('/subscription-expired')
            ->assertOk();

        $this->assertStringContainsString("'CompleteRegistration'", $resposta->getContent(), 'a página das pendentes tem o pixel com o evento');
        $this->assertStringContainsString('registration-999', $resposta->getContent());
    }

    public function test_o_resumo_separa_as_medidas_e_deixa_os_testes_de_fora(): void
    {
        [$desde, $ate] = [now()->subDay(), now()->addDay()];

        // Duas inscrições comerciais (uma já entrou), uma de teste (email marcado),
        // e um evento repetido da MESMA empresa (defeito antigo): conta uma vez.
        $real1 = $this->conversao('Padaria Boa', 'dono@padaria.ao', 'pacote-vendas', entrou: true);
        $this->eventoRepetido($real1); // duplicado do mesmo tenant
        $this->conversao('Oficina Kwanza', 'geral@kwanza.ao', 'pacote-oficina', entrou: false);
        $this->conversao('Empresa Teste', 'teste@exemplo.ao', 'pacote-rh', entrou: true); // TESTE

        $r = ResultadosDaCampanha::resumo($desde, $ate);

        $this->assertSame(4, $r['eventos_completeregistration'], 'quatro disparos: 2 reais + 1 repetido + 1 teste');
        $this->assertSame(2, $r['inscricoes_unicas'], 'duas inscrições comerciais (o repetido não conta duas vezes)');
        $this->assertSame(2, $r['empresas_criadas']);
        $this->assertSame(1, $r['primeira_utilizacao'], 'só uma das comerciais já criou trabalho');
        $this->assertSame(1, $r['testes_excluidos']);

        // As linhas não levam dados pessoais.
        foreach ($r['linhas'] as $linha) {
            $this->assertArrayNotHasKey('email', $linha);
            $this->assertArrayNotHasKey('nif', $linha);
            $this->assertArrayNotHasKey('empresa', $linha);
        }
    }

    public function test_entrar_nao_e_primeira_utilizacao_criar_trabalho_e(): void
    {
        [$desde, $ate] = [now()->subDay(), now()->addDay()];

        // Entrou (o registo inicia sessão sozinho) mas não fez nada.
        $parado = $this->conversao('Loja Parada', 'loja@parada.ao', 'pacote-vendas', entrou: false);
        User::where('email', 'loja@parada.ao')->update(['last_login_at' => now()]);

        $linha = ResultadosDaCampanha::linhas($desde, $ate)->firstWhere('tenant_id', $parado->id);
        $this->assertFalse($linha['primeira_utilizacao'], 'o login do próprio registo não é utilização');

        // O que o registo provisiona no mesmo segundo também não conta.
        \Illuminate\Support\Facades\DB::table('invoicing_clients')->insert([
            'tenant_id' => $parado->id, 'name' => 'Consumidor Final', 'nif' => '999999999',
            'created_at' => $parado->created_at->copy()->addMinutes(5), 'updated_at' => now(),
        ]);
        $this->assertNull(\App\Services\Campanha\PrimeiraUtilizacao::de($parado), 'o Consumidor Final não é trabalho');
    }

    /** Cria uma empresa + dono + o evento de conversão, e devolve o tenant. */
    private function conversao(string $empresa, string $email, string $plano, bool $entrou): Tenant
    {
        $tenant = Tenant::create([
            'name' => $empresa, 'slug' => \Illuminate\Support\Str::slug($empresa) . '-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => $email, 'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Dono ' . $empresa, 'email' => $email, 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'is_active' => true,
            'last_login_at' => now(),
        ]);

        // «Entrou» = criou trabalho a sério, depois do registo.
        if ($entrou) {
            \Illuminate\Support\Facades\DB::table('invoicing_products')->insert([
                'tenant_id' => $tenant->id, 'name' => 'Pão', 'code' => 'P-' . uniqid(), 'type' => 'produto',
                'price' => 100, 'unit' => 'UN', 'is_active' => true,
                'created_at' => $tenant->created_at->copy()->addMinutes(3), 'updated_at' => now(),
            ]);
        }

        AnalyticsEvent::create([
            'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'conversion', 'event_name' => 'complete_registration',
            'path' => '/register', 'utm_source' => 'meta', 'utm_campaign' => 'set',
            'user_id' => $user->id,
            'meta' => ['tenant_id' => $tenant->id, 'plan_slug' => $plano, 'subscription_status' => 'trial'],
            'created_at' => now(),
        ]);

        return $tenant;
    }

    private function eventoRepetido(Tenant $tenant): void
    {
        AnalyticsEvent::create([
            'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'conversion', 'event_name' => 'complete_registration', 'path' => '/register',
            'meta' => ['tenant_id' => $tenant->id, 'plan_slug' => 'pacote-vendas', 'subscription_status' => 'trial'],
            'created_at' => now(),
        ]);
    }
}

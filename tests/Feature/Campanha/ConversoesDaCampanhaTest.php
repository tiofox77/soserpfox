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

        // O event_id é determinístico (uma empresa = uma conversão), e ficou em flash.
        $this->assertSame('registration-' . $tenant->id, session('meta_registration_completed.event_id'));

        // Recarregar a página do registo consome o flash: não volta a disparar.
        $this->get('/register')->assertOk();
        $this->assertNull(session('meta_registration_completed'));
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
        $this->assertSame(1, $r['primeira_utilizacao'], 'só uma das comerciais já entrou');
        $this->assertSame(1, $r['testes_excluidos']);
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
            'last_login_at' => $entrou ? now() : null,
        ]);

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

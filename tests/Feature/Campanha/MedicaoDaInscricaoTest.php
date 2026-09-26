<?php

namespace Tests\Feature\Campanha;

use App\Models\AnalyticsEvent;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Campanha\EventosDaInscricao;
use App\Support\Robos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A MEDIÇÃO DA INSCRIÇÃO, PASSO A PASSO (26/09/2026).
 *
 * O painel chamava «registos» a cliques em links para `/register`. Agora cada
 * passo tem a sua fonte: o clique (browser, sem robôs), o formulário iniciado
 * (servidor, uma vez por sessão), a empresa criada, o teste e a primeira
 * utilização.
 */
class MedicaoDaInscricaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function evento(array $por = []): array
    {
        return array_merge([
            'visitor_id' => (string) Str::uuid(), 'session_id' => (string) Str::uuid(),
            'type' => 'cta_click', 'event_name' => 'click_register', 'path' => '/modulos/vendas',
        ], $por);
    }

    public function test_o_robo_de_anuncios_da_meta_nao_conta_como_visita(): void
    {
        $antes = AnalyticsEvent::count();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; meta-externalads/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler))')
            ->postJson('/api/analytics/track', $this->evento())->assertOk();
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0')
            ->postJson('/api/analytics/track', $this->evento())->assertOk();

        $this->assertSame($antes, AnalyticsEvent::count(), 'robôs e pré-visualizações não se gravam');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/128 Mobile Safari/537.36')
            ->postJson('/api/analytics/track', $this->evento())->assertOk();

        $this->assertSame($antes + 1, AnalyticsEvent::count(), 'uma pessoa conta');
        $this->assertTrue(Robos::eRobo(''), 'sem user agent não é um browser');
    }

    public function test_a_pagina_do_modulo_guarda_o_modulo_de_entrada(): void
    {
        $this->get('/modulos/oficina')->assertOk();

        $this->assertSame('oficina', session('registration_module'));
        $this->assertSame(config('campanha.modulos.oficina'), session('registration_plan'));
    }

    public function test_o_formulario_iniciado_grava_se_uma_vez_por_sessao(): void
    {
        $this->withSession(['registration_module' => 'vendas', 'registration_acquisition' => ['utm_source' => 'facebook']]);

        EventosDaInscricao::iniciado('pacote-vendas');
        EventosDaInscricao::iniciado('pacote-vendas');

        $iniciados = AnalyticsEvent::where('event_name', EventosDaInscricao::INICIADO)->get();
        $this->assertCount(1, $iniciados, 'duas etapas válidas = um formulário iniciado');
        $this->assertSame('vendas', $iniciados->first()->meta['modulo']);
        $this->assertSame('facebook', $iniciados->first()->utm_source);
        $this->assertNull($iniciados->first()->user_agent, 'sem consentimento de estatísticas não se guarda o browser');
    }

    public function test_a_primeira_etapa_valida_do_assistente_grava_o_inicio(): void
    {
        $antes = AnalyticsEvent::where('event_name', EventosDaInscricao::INICIADO)->count();

        // Uma etapa com erros não é um início.
        $this->postJson('/register/seguinte', ['passo' => 1, 'name' => '', 'email' => 'x'])->assertStatus(422);
        $this->assertSame($antes, AnalyticsEvent::where('event_name', EventosDaInscricao::INICIADO)->count());

        $this->postJson('/register/seguinte', [
            'passo' => 1, 'name' => 'Ana Silva', 'email' => 'ana' . uniqid() . '@cliente.ao',
            'password' => 'segredo-forte-123', 'password_confirmation' => 'segredo-forte-123',
        ])->assertOk();

        $this->assertSame($antes + 1, AnalyticsEvent::where('event_name', EventosDaInscricao::INICIADO)->count());
    }

    public function test_o_funil_da_analitica_separa_o_clique_do_registo(): void
    {
        $dono = \App\Models\User::create([
            'name' => 'Dono', 'email' => 'dono' . uniqid() . '@soserp.vip', 'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $dono->forceFill(['is_super_admin' => true])->save();

        $resposta = $this->actingAs($dono)->getJson('/api/v1/plataforma/react/analitica?periodo=30d')->assertOk();
        $degraus = array_column($resposta->json('funil'), 'degrau');

        $this->assertContains('Clicou para registo', $degraus);
        $this->assertNotContains('Começou o registo', $degraus, 'um clique não é um registo');
        foreach (['Iniciou o formulário', 'Criou a empresa', 'Activou o teste', 'Primeira utilização'] as $passo) {
            $this->assertContains($passo, $degraus);
        }
    }
}
